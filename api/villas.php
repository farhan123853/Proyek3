<?php
/**
 * API: Daftar Tipe Villa (JSON) — Villa Joglo Masayu
 * Kebutuhan: F-14
 * Mengirimkan data master tipe villa dari database MySQL
 */

require_once __DIR__ . '/../includes/bootstrap.php';

header('Content-Type: application/json; charset=UTF-8');

try {
    $pdo = get_db();
    $stmt = $pdo->query("SELECT * FROM `tipe_villa` ORDER BY `id_tipe` ASC");
    $rows = $stmt->fetchAll();

    $villas = [];
    $num = 1;

    // Foto fallback sesuai aset frontend
    $defaultPhotos = [
        'joglo-wulung' => [
            'https://images.unsplash.com/photo-1582719508461-905c673771fd?w=1200&q=80',
            'https://images.unsplash.com/photo-1571896349842-33c89424de2d?w=1200&q=80',
            'https://images.unsplash.com/photo-1566073771259-6a8506099945?w=1200&q=80',
            'https://images.unsplash.com/photo-1520250497591-112f2f40a3f4?w=1200&q=80'
        ],
        'joglo-saren' => [
            'https://images.unsplash.com/photo-1540541338287-41700207dee6?w=1200&q=80',
            'https://images.unsplash.com/photo-1600585154340-be6161a56a0c?w=1200&q=80',
            'https://images.unsplash.com/photo-1578898887932-dce23a595ad4?w=1200&q=80',
            'https://images.unsplash.com/photo-1513694203232-719a280e022f?w=1200&q=80'
        ],
        'joglo-agung' => [
            'https://images.unsplash.com/photo-1445019980597-93fa8acb246c?w=1200&q=80',
            'https://images.unsplash.com/photo-1551882547-ff40c4fe1fa7?w=1200&q=80',
            'https://images.unsplash.com/photo-1582719508461-905c673771fd?w=1200&q=80',
            'https://images.unsplash.com/photo-1571896349842-33c89424de2d?w=1200&q=80'
        ]
    ];

    foreach ($rows as $row) {
        $slug = $row['slug'];
        $fasilitasArr = json_decode($row['fasilitas'] ?? '[]', true) ?: [];

        $numStr = str_pad((string)$num, 2, '0', STR_PAD_LEFT);
        $villas[] = [
            'id'          => $slug,
            'slug'        => $slug,
            'num'         => $numStr,
            'name'        => $row['nama'],
            'code'        => 'Villa ' . $numStr,
            'photos'      => $defaultPhotos[$slug] ?? $defaultPhotos['joglo-wulung'],
            'price'       => [
                'weekday'  => [
                    'id' => 'Rp ' . number_format($row['harga_weekday'], 0, ',', '.'),
                    'en' => 'IDR ' . number_format($row['harga_weekday'], 0, '.', ',')
                ],
                'weekend'  => [
                    'id' => 'Rp ' . number_format($row['harga_weekend'], 0, ',', '.'),
                    'en' => 'IDR ' . number_format($row['harga_weekend'], 0, '.', ',')
                ],
                'extrabed' => [
                    'id' => 'Rp ' . number_format($row['harga_extrabed'], 0, ',', '.'),
                    'en' => 'IDR ' . number_format($row['harga_extrabed'], 0, '.', ',')
                ],
            ],
            'priceFrom'   => [
                'id' => 'Rp ' . number_format($row['harga_weekday'], 0, ',', '.'),
                'en' => 'IDR ' . number_format($row['harga_weekday'], 0, '.', ',')
            ],
            'capacity'    => [
                'std' => (int)$row['kapasitas_standar'],
                'max' => (int)$row['kapasitas_maks']
            ],
            'meta'        => [
                'id' => "{$row['kapasitas_standar']} tamu · fasilitas lengkap",
                'en' => "{$row['kapasitas_standar']} guests · full facilities"
            ],
            'tagline'     => [
                'id' => "Keindahan arsitektur autentik {$row['nama']}.",
                'en' => "Authentic architectural beauty of {$row['nama']}."
            ],
            'description' => [
                'id' => $row['deskripsi'] ?? '',
                'en' => $row['deskripsi'] ?? ''
            ],
            'facilities'  => [
                'id' => $fasilitasArr,
                'en' => $fasilitasArr
            ]
        ];
        $num++;
    }

    echo json_encode($villas, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => true,
        'message' => 'Gagal memuat data tipe villa dari basis data.'
    ]);
}
