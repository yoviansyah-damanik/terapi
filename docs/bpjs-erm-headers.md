# Header HTTP pada Pengiriman eRM

## Status: ✅ Sudah Otomatis Ditambahkan

Header `X-cons-id`, `X-timestamp`, dan `X-signature` **sudah otomatis** ditambahkan pada setiap pengiriman eRM ke BPJS melalui `BpjsBaseService::headers()`.

## Header yang Dikirim

```
X-cons-id: [Consumer ID BPJS]
X-timestamp: [Unix timestamp]
X-signature: [HMAC-SHA256 signature base64]
user_key: [User key BPJS]
Content-Type: application/json
```

## Cara Kerja

1. **ErmService** extends **BpjsBaseService**
2. Method `insertErm()` memanggil `$this->post()`
3. Method `post()` otomatis menggunakan `$this->headers()`
4. Headers otomatis ditambahkan pada setiap request

## Signature Generation

```php
$signature = base64_encode(
    hash_hmac('sha256', $consId . '&' . $timestamp, $secretKey, true)
);
```

## Tidak Perlu Modifikasi

✅ Header sudah otomatis ditambahkan  
✅ Signature otomatis di-generate  
✅ Timestamp otomatis di-update setiap request
