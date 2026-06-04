<?php
// Minimal categories JSON endpoint for local dev
header('Content-Type: application/json');
// Example categories — adjust/slurp from real service in production
$categories = [
    ['name' => 'Ensemble Lin', 'slug' => 'ensemble-lin', 'count' => 12],
    ['name' => 'Nouveautés', 'slug' => 'nouveautes', 'count' => 8],
    ['name' => 'Pantalons', 'slug' => 'pants', 'count' => 6],
    ['name' => 'Shorts', 'slug' => 'shorts', 'count' => 4],
];
echo json_encode($categories);
