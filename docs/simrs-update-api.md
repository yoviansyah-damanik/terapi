# Panduan Integrasi API Update SIMRS

## Ringkasan

Sistem ini menyediakan API untuk distribusi update otomatis ke aplikasi SIMRS.
Alur kerja: **Cek Versi → Unduh → Verifikasi Checksum → Pasang Update → Lapor Hasil**

---

## Autentikasi

Semua endpoint menggunakan **Bearer Token** dengan scope `update-simrs`.

```
Authorization: Bearer <token>
```

Token diperoleh dari administrator sistem melalui menu **API Portal → Manajemen API**.

---

## Endpoint

### 1. Cek Versi Aktif

```
GET /api/simrs/version
```

**Response:**

```json
{
    "version": "2.5.1",
    "notes": "Daftar perubahan...",
    "checksum": "a3f2c1d4e5b6...",
    "file_size": 52428800,
    "released_at": "2026-03-20T07:00:00+07:00"
}
```

### 2. Unduh File Update

```
GET /api/simrs/download/{version}
```

File ZIP dikembalikan sebagai binary. Header response berisi checksum:

```
X-Checksum-SHA256: a3f2c1d4e5b6...
```

**Throttle:** maksimal 5 unduhan per menit per token.

### 3. Laporan Hasil Update

```
POST /api/simrs/update/report
Content-Type: application/json
```

**Request body:**

```json
{
    "status": "success",
    "from_version": "2.4.0",
    "to_version": "2.5.1",
    "duration_seconds": 45,
    "host_name": "SIMRS-SERVER-1",
    "app_name": "SIMRS RSU",
    "error_message": null
}
```

- `status`: `"success"` | `"failed"` | `"rollback"`
- `error_message`: wajib diisi jika status `"failed"`

---

## Alur Implementasi (Pseudocode)

```
function checkAndApplyUpdate():
    currentVersion = getInstalledVersion()

    // Langkah 1: Cek versi
    response = GET /api/simrs/version
    if response.status != 200: return

    serverVersion = response.body.version
    if serverVersion == currentVersion: return  // sudah terkini

    // Langkah 2: Unduh file
    file = GET /api/simrs/download/{serverVersion}
    expectedChecksum = file.headers["X-Checksum-SHA256"]

    // Langkah 3: Verifikasi checksum
    actualChecksum = sha256(file.body)
    if actualChecksum != expectedChecksum:
        reportUpdate(status="failed", error="Checksum tidak cocok")
        return

    // Langkah 4: Pasang update
    startTime = now()
    success = applyUpdate(file.body)
    duration = now() - startTime

    // Langkah 5: Lapor hasil
    if success:
        reportUpdate(status="success", fromVersion=currentVersion,
                     toVersion=serverVersion, duration=duration)
    else:
        rollback()
        reportUpdate(status="rollback", fromVersion=currentVersion,
                     toVersion=serverVersion, error=getLastError())

function reportUpdate(status, fromVersion, toVersion, duration, error):
    POST /api/simrs/update/report
    body = {
        "status": status,
        "from_version": fromVersion,
        "to_version": toVersion,
        "duration_seconds": duration,
        "host_name": getHostName(),
        "app_name": "SIMRS",
        "error_message": error
    }
```

---

## Contoh Implementasi Java

```java
import java.net.*;
import java.net.http.*;
import java.nio.file.*;
import java.security.*;
import com.google.gson.*;

public class SimrsUpdater {
    private static final String BASE_URL = "https://terapi.example.com";
    private static final String TOKEN = "your-api-token-here";
    private final HttpClient client = HttpClient.newHttpClient();

    /** Cek dan terapkan update jika tersedia */
    public void checkAndUpdate(String currentVersion) throws Exception {
        // 1. Cek versi
        HttpRequest req = HttpRequest.newBuilder()
            .uri(URI.create(BASE_URL + "/api/simrs/version"))
            .header("Authorization", "Bearer " + TOKEN)
            .GET().build();

        HttpResponse<String> res = client.send(req, HttpResponse.BodyHandlers.ofString());
        if (res.statusCode() != 200) return;

        JsonObject body = JsonParser.parseString(res.body()).getAsJsonObject();
        String serverVersion = body.get("version").getAsString();

        if (serverVersion.equals(currentVersion)) return; // sudah terkini

        // 2. Unduh
        Path tempFile = downloadUpdate(serverVersion);
        if (tempFile == null) return;

        // 3. Verifikasi checksum
        String serverChecksum = body.get("checksum").getAsString();
        String localChecksum  = sha256(tempFile);

        if (!serverChecksum.equals(localChecksum)) {
            reportUpdate("failed", currentVersion, serverVersion, 0, "Checksum mismatch");
            Files.deleteIfExists(tempFile);
            return;
        }

        // 4. Terapkan update
        long start = System.currentTimeMillis();
        boolean ok = applyUpdate(tempFile);
        int duration = (int) ((System.currentTimeMillis() - start) / 1000);

        // 5. Lapor
        if (ok) {
            reportUpdate("success", currentVersion, serverVersion, duration, null);
        } else {
            reportUpdate("rollback", currentVersion, serverVersion, duration, "Update process failed");
        }
    }

    private Path downloadUpdate(String version) throws Exception {
        HttpRequest req = HttpRequest.newBuilder()
            .uri(URI.create(BASE_URL + "/api/simrs/download/" + version))
            .header("Authorization", "Bearer " + TOKEN)
            .GET().build();

        Path tempFile = Files.createTempFile("update-simrs-", ".zip");
        HttpResponse<Path> res = client.send(req, HttpResponse.BodyHandlers.ofFile(tempFile));

        return res.statusCode() == 200 ? tempFile : null;
    }

    private String sha256(Path file) throws Exception {
        MessageDigest md = MessageDigest.getInstance("SHA-256");
        byte[] bytes = Files.readAllBytes(file);
        byte[] hash  = md.digest(bytes);
        StringBuilder sb = new StringBuilder();
        for (byte b : hash) sb.append(String.format("%02x", b));
        return sb.toString();
    }

    private void reportUpdate(String status, String from, String to,
                               int duration, String error) {
        try {
            String hostname = InetAddress.getLocalHost().getHostName();
            JsonObject payload = new JsonObject();
            payload.addProperty("status",           status);
            payload.addProperty("from_version",     from);
            payload.addProperty("to_version",       to);
            payload.addProperty("duration_seconds", duration);
            payload.addProperty("host_name",        hostname);
            payload.addProperty("app_name",         "SIMRS");
            if (error != null) payload.addProperty("error_message", error);

            HttpRequest req = HttpRequest.newBuilder()
                .uri(URI.create(BASE_URL + "/api/simrs/update/report"))
                .header("Authorization", "Bearer " + TOKEN)
                .header("Content-Type", "application/json")
                .POST(HttpRequest.BodyPublishers.ofString(payload.toString()))
                .build();

            client.send(req, HttpResponse.BodyHandlers.discarding());
        } catch (Exception ignored) {
            // Jangan gagalkan proses utama jika laporan gagal terkirim
        }
    }

    private boolean applyUpdate(Path zipFile) {
        // Implementasi sesuai mekanisme update SIMRS
        // Contoh: unzip ke direktori SIMRS, restart service
        return true;
    }
}
```

---

## Catatan Penting

1. **Jadwalkan pengecekan** minimal 1x per hari (misalnya saat startup SIMRS).
2. **Jangan blok UI** — jalankan `checkAndUpdate()` di background thread.
3. **Simpan token dengan aman** — gunakan konfigurasi terenkripsi, bukan hardcode.
4. **Laporan wajib dikirim** meski update gagal — untuk monitoring di sisi server.
5. **Checksum SHA-256 harus diverifikasi** sebelum menjalankan file ZIP.
