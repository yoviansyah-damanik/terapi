<?php

namespace Database\Seeders;

use App\Models\Terminology\SnomedSemanticTag;
use Illuminate\Database\Seeder;

class SnomedSemanticTagSeeder extends Seeder
{
    public function run(): void
    {
        $tags = [
            ['tag' => 'finding', 'description' => 'Clinical finding'],
            ['tag' => 'procedure', 'description' => 'Procedure'],
            ['tag' => 'situation', 'description' => 'Situation with explicit context'],
            ['tag' => 'event', 'description' => 'Event'],
            ['tag' => 'disorder', 'description' => 'Disorder'],
            ['tag' => 'organism', 'description' => 'Organism'],
            ['tag' => 'substance', 'description' => 'Substance'],
            ['tag' => 'product', 'description' => 'Pharmaceutical / biologic product'],
            ['tag' => 'specimen', 'description' => 'Specimen'],
            ['tag' => 'observable entity', 'description' => 'Observable entity'],
            ['tag' => 'body structure', 'description' => 'Body structure'],
            ['tag' => 'occupation', 'description' => 'Occupation'],
            ['tag' => 'regime/therapy', 'description' => 'Regime/Therapy'],
            ['tag' => 'qualification value', 'description' => 'Qualifier value'],
            ['tag' => 'environment', 'description' => 'Environment'],
            ['tag' => 'geographic location', 'description' => 'Geographic location'],
            ['tag' => 'physical object', 'description' => 'Physical object'],
            ['tag' => 'attribute', 'description' => 'Attribute'],
            ['tag' => 'linkage concept', 'description' => 'Linkage concept'],
            ['tag' => 'navigational concept', 'description' => 'Navigational concept'],
            ['tag' => 'record artifact', 'description' => 'Record artifact'],
            ['tag' => 'special concept', 'description' => 'Special concept'],
            ['tag' => 'staging scale', 'description' => 'Staging scale'],
            ['tag' => 'social concept', 'description' => 'Social concept'],
        ];

        foreach ($tags as $tag) {
            SnomedSemanticTag::updateOrCreate(
                ['tag' => $tag['tag']],
                ['description' => $tag['description'], 'active' => true]
            );
        }
    }
}
