# DICOM Worklist API Documentation

API untuk integrasi SIMRS dengan sistem PACS/Orthanc melalui Orthanc-Sync Bridge.

## Base URL
`/api/v1/worklists`

## Authentication
Bearer Token dengan scope `dicom`.

## Endpoints

### 1. Batch Worklist
**POST** `/api/v1/worklists/batch`

Mengirim beberapa order SIMRS sekaligus ke antrean PACS.

**Request Body:**
```json
[
  {
    "accession_number": "RAD20260514001",
    "type": "radiologi",
    "bypass": false
  },
  {
    "accession_number": "USG20260514002",
    "type": "usg"
  }
]
```

### 2. Single Worklist
**POST** `/api/v1/worklists`

Mengirim satu order SIMRS ke antrean PACS.

**Request Body:**
```json
{
  "accession_number": "RAD20260514001",
  "type": "radiologi",
  "bypass": false
}
```

### Parameter Deskripsi
| Parameter | Tipe | Wajib | Deskripsi |
|-----------|------|-------|-----------|
| `accession_number` | string | Ya | Nomor Order dari SIMRS. |
| `type` | string | Tidak | `radiologi` (default) atau `usg`. Menentukan sumber data di SIMRS. |
| `bypass` | boolean | Tidak | Jika `true`, akan menghapus data lama di PACS sebelum membuat yang baru. |

### 3. Get Status
**GET** `/api/v1/worklists/{accession_number}`

Mengecek status worklist di lokal dan status sinkronisasi di Orthanc.

### 4. Delete Worklist
**DELETE** `/api/v1/worklists/{accession_number}`

Menghapus data worklist dari database lokal (tidak menghapus file di PACS).

## Response Format

**Success (200 OK):**
```json
{
  "success": true,
  "message": "Order berhasil diproses",
  "data": {
    "total": 1,
    "success_count": 1,
    "results": [...]
  }
}
```

**Error (422 Unprocessable Entity):**
```json
{
  "success": false,
  "message": "Validasi gagal",
  "errors": { ... }
}
```
