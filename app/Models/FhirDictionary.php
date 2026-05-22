<?php

namespace App\Models;

use App\Services\SatuSehat\FhirDictionary as SatuSehatFhirDictionary;
use Illuminate\Database\Eloquent\Model;

class FhirDictionary extends Model
{
    protected $fillable = [
        'type',
        'system_code',
        'system_term',
        'system_defenition',
        'system_display',
        'source',
    ];

    /**
     * Scope untuk memfilter berdasarkan sumber terminology.
     */
    public function scopeFromSource($query, string $source)
    {
        return $query->where('source', $source);
    }

    public static function getDistinctSources(): array
    {
        return ['internal', 'hl7', 'kemkes', 'atc', 'ucum', 'other'];
    }

    public static function getDistinctTypes(?string $source = null): array
    {
        return self::query()
            ->when($source, fn($q) => $q->where('source', $source))
            ->distinct()
            ->orderBy('type')
            ->pluck('type')
            ->toArray();
    }

    /**
     * Mendaftarkan referensi KFA secara otomatis ke dalam kamus (jika blm ada).
     */
    public static function registerDefault(string $type, string $code, string $name, string $source = 'internal', ?string $display = null): void
    {
        if (empty($code)) {
            return;
        }

        self::updateOrCreate(
            ['source' => $source, 'type' => $type, 'system_code' => $code],
            ['system_term' => $name, 'system_display' => $display]
        );
    }

    /**
     * Mengkonversi uom name bahasa Indonesia menjadi HL7 Terminology.
     */
    public static function standardizeDenominator(?string $code = null, string $name): ?array
    {
        $map = [
            [
                'code' => 'TAB',
                'name' => 'Tablet',
                'includes' => [
                    'tablet',
                    'tab',
                    'keping',
                    'kaplet',
                    'pil'
                ]
            ],
            [
                'code' => 'CAP',
                'name' => 'Capsule',
                'includes' => [
                    'kapsul',
                    'capsule',
                    'cap',
                    'caps'
                ]
            ],
            [
                'code' => 'SOL',
                'name' => 'Solution',
                'includes' => [
                    'solution',
                    'sol',
                    'larutan',
                    'injeksi',
                    'injection',
                    'infus',
                    'iv',
                    'iv drip',
                    'drip',
                    'ampul',
                    'ampoule',
                    'vial',
                    'intravena'
                ]
            ],
            [
                'code' => 'DROP',
                'name' => 'Drops',
                'includes' => [
                    'drop',
                    'drops',
                    'tetes',
                    'tetes mata',
                    'tetes telinga',
                    'tetes hidung'
                ]
            ],
            [
                'code' => 'SPRY',
                'name' => 'Sprays',
                'includes' => [
                    'spray',
                    'semprot',
                    'spray hidung',
                    'spray mulut'
                ]
            ],
            [
                'code' => 'INHL',
                'name' => 'Inhalant',
                'includes' => [
                    'inhaler',
                    'inhalasi',
                    'nebule',
                    'nebulizer',
                    'neb',
                    'gas inhalasi'
                ]
            ],
            [
                'code' => 'AER',
                'name' => 'Aerosol',
                'includes' => [
                    'aerosol',
                    'aer'
                ]
            ],
            [
                'code' => 'CREAM',
                'name' => 'Cream',
                'includes' => [
                    'krim',
                    'cream',
                    'creme'
                ]
            ],
            [
                'code' => 'OINT',
                'name' => 'Ointment',
                'includes' => [
                    'salep',
                    'ointment',
                    'unguentum',
                    'ungt'
                ]
            ],
            [
                'code' => 'GEL',
                'name' => 'Gel',
                'includes' => [
                    'gel',
                    'jeli'
                ]
            ],
            [
                'code' => 'PASTE',
                'name' => 'Paste',
                'includes' => [
                    'paste',
                    'pasta'
                ]
            ],
            [
                'code' => 'SUPP',
                'name' => 'Suppository',
                'includes' => [
                    'suppositoria',
                    'suppository',
                    'sup',
                    'rektal',
                    'vaginal supp'
                ]
            ],
            [
                'code' => 'SYRUP',
                'name' => 'Syrup',
                'includes' => [
                    'sirup',
                    'syrup',
                    'sir',
                    'oral liquid',
                    'cair oral'
                ]
            ],
            [
                'code' => 'SUSP',
                'name' => 'Suspension',
                'includes' => [
                    'suspensi',
                    'suspension',
                    'susp'
                ]
            ],
            [
                'code' => 'EMUL',
                'name' => 'Emulsion',
                'includes' => [
                    'emulsi',
                    'emulsion'
                ]
            ],
            [
                'code' => 'POWD',
                'name' => 'Powder',
                'includes' => [
                    'serbuk',
                    'powder',
                    'puyer',
                    'pulvis'
                ]
            ],
            [
                'code' => 'GRAN',
                'name' => 'Granules',
                'includes' => [
                    'granul',
                    'granule',
                    'granules'
                ]
            ],
            [
                'code' => 'LOZ',
                'name' => 'Lozenge',
                'includes' => [
                    'lozenge',
                    'tablet hisap',
                    'hisap'
                ]
            ],
            [
                'code' => 'CHEWTAB',
                'name' => 'Chewable Tablet',
                'includes' => [
                    'chewable',
                    'tablet kunyah',
                    'kunyah'
                ]
            ],
            [
                'code' => 'EFFTAB',
                'name' => 'Effervescent Tablet',
                'includes' => [
                    'effervescent',
                    'tablet effervescent',
                    'tablet larut'
                ]
            ],
            [
                'code' => 'PATCH',
                'name' => 'Transdermal Patch',
                'includes' => [
                    'patch',
                    'plester obat',
                    'koyo'
                ]
            ]
        ];

        $lower = strtolower(trim($name));

        foreach ($map as $item) {
            foreach ($item['includes'] as $alias) {
                if ($lower === strtolower($alias)) {
                    return [
                        'code' => $item['code'],
                        'name' => $item['name'],
                        'system' => 'http://terminology.hl7.org/CodeSystem/v3-orderableDrugForm'
                    ];
                }
            }
        }

        return [
            'code' => $code,
            'name' => $name,
            'system' => 'http://terminology.hl7.org/CodeSystem/v3-orderableDrugForm'
        ];
    }

    /**
     * Mengkonversi uom name bahasa Indonesia menjadi ATC Terminology (http://www.whocc.no/atc).
     */
    public static function standardizeNumerator(string $name): ?array
    {
        $map = [];

        $lower = strtolower(trim($name));

        foreach ($map as $item) {
            foreach ($item['includes'] as $alias) {
                if ($lower === strtolower($alias)) {
                    return [
                        'code' => $item['code'],
                        'name' => $item['name'],
                        'system' => 'http://terminology.hl7.org/CodeSystem/v3-orderableDrugForm'
                    ];
                }
            }
        }

        return null;
    }
}
