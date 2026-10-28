<?php

namespace Database\Seeders;

use App\Models\Board;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    // Two companies, each with its own job board. Safe to run on every boot.
    public function run(): void
    {
        $boards = [
            'acme' => [
                'name' => 'Acme Robotics',
                'tagline' => 'We build warehouse robots. Come build them with us.',
                'jobs' => [
                    ['Firmware Engineer', 'Remote (EU)', 'Write the C++ that keeps 2,000 robots moving.'],
                    ['Field Technician', 'Rotterdam', 'Install and service robots at customer sites.'],
                ],
            ],
            'globex' => [
                'name' => 'Globex Logistics',
                'tagline' => 'Freight software for teams that ship.',
                'jobs' => [
                    ['Product Designer', 'Remote (US)', 'Design the tools dispatchers use all day.'],
                    ['Support Lead', 'Austin, TX', 'Run a small team that answers carriers fast.'],
                ],
            ],
        ];

        foreach ($boards as $slug => $data) {
            $board = Board::firstOrCreate(
                ['slug' => $slug],
                ['name' => $data['name'], 'tagline' => $data['tagline']],
            );

            if ($board->wasRecentlyCreated) {
                foreach ($data['jobs'] as [$title, $location, $description]) {
                    $board->jobPostings()->create(compact('title', 'location', 'description'));
                }
            }
        }
    }
}
