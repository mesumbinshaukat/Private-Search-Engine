<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\ParsedRecord;

class TestRecordSeeder extends Seeder
{
    public function run()
    {
        for($i=1; $i<=10; $i++) {
            ParsedRecord::create([
                'url' => 'https://example.com/'.$i,
                'canonical_url' => 'https://example.com/'.$i,
                'title' => 'Sample Search Result '.$i,
                'description' => 'This is a sample description for search result ' . $i . '. It contains keywords for testing.',
                'category' => 'technology',
                'content_hash' => md5('test-'.$i),
                'parsed_at' => now(),
            ]);
        }
        
        $this->command->info('10 sample records seeded for technology category.');
    }
}
