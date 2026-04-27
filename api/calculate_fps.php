<?php
require_once '../config.php';

header('Content-Type: application/json');

$componentId = $_GET['component_id'] ?? '';
$gameId = $_GET['game_id'] ?? '';
$resolution = $_GET['resolution'] ?? '1920x1080';

if (!$componentId || !$gameId) {
    echo json_encode(['error' => 'Missing parameters']);
    exit;
}

function interpolateFps(int $score, array $anchors): float
{
    if ($score <= $anchors[0]['score']) {
        return $anchors[0]['fps'] * max($score, 1000) / $anchors[0]['score'];
    }

    $lastAnchor = end($anchors);
    if ($score >= $lastAnchor['score']) {
        return $lastAnchor['fps'] + ($score - $lastAnchor['score']) * 0.02;
    }

    for ($i = 0; $i < count($anchors) - 1; $i++) {
        $current = $anchors[$i];
        $next = $anchors[$i + 1];
        if ($score >= $current['score'] && $score <= $next['score']) {
            $rangeScore = $next['score'] - $current['score'];
            $rangeFps = $next['fps'] - $current['fps'];
            $progress = ($score - $current['score']) / ($rangeScore ?: 1);
            return $current['fps'] + $rangeFps * $progress;
        }
    }

    // fallback (не должно достигаться)
    return 60.0;
}

function clampValue(float $value, float $min, float $max): float
{
    return max($min, min($max, $value));
}

function scaleBenchmarkRow(array $row, float $scale, string $sourceType = 'estimated'): array
{
    $avg = (int) round(($row['avg_fps'] ?? 0) * $scale);
    $min = (int) round(($row['min_fps'] ?? 0) * $scale);
    $max = (int) round(($row['max_fps'] ?? 0) * $scale);

    $avg = (int) clampValue($avg, 10, 999);
    $min = (int) clampValue(min($min, max($avg - 1, 1)), 1, 950);
    $max = (int) clampValue(max($max, $avg + 1), 1, 999);

    return [
        'avg_fps' => $avg,
        'min_fps' => $min,
        'max_fps' => $max,
        'estimated' => true,
        'source_type' => $sourceType,
        'source_settings' => $row['settings'] ?? null
    ];
}

function estimateFromBenchmarkPool(int $score, array $rows): ?array
{
    if (!$rows) {
        return null;
    }

    usort($rows, static function ($left, $right) {
        return (int) $left['performance_score'] <=> (int) $right['performance_score'];
    });

    $first = $rows[0];
    $last = $rows[count($rows) - 1];

    foreach ($rows as $row) {
        if ((int) $row['performance_score'] === $score) {
            return scaleBenchmarkRow($row, 1.0, 'benchmark-match');
        }
    }

    if ($score <= (int) $first['performance_score']) {
        $scale = pow(max($score, 1000) / max((int) $first['performance_score'], 1), 0.92);
        return scaleBenchmarkRow($first, $scale, 'benchmark-downscale');
    }

    if ($score >= (int) $last['performance_score']) {
        $scale = pow($score / max((int) $last['performance_score'], 1), 0.90);
        return scaleBenchmarkRow($last, $scale, 'benchmark-upscale');
    }

    for ($i = 0; $i < count($rows) - 1; $i++) {
        $lower = $rows[$i];
        $upper = $rows[$i + 1];
        $lowerScore = (int) $lower['performance_score'];
        $upperScore = (int) $upper['performance_score'];

        if ($score >= $lowerScore && $score <= $upperScore) {
            $distance = max($upperScore - $lowerScore, 1);
            $progress = ($score - $lowerScore) / $distance;

            $avg = ($lower['avg_fps'] ?? 0) + (($upper['avg_fps'] ?? 0) - ($lower['avg_fps'] ?? 0)) * $progress;
            $min = ($lower['min_fps'] ?? 0) + (($upper['min_fps'] ?? 0) - ($lower['min_fps'] ?? 0)) * $progress;
            $max = ($lower['max_fps'] ?? 0) + (($upper['max_fps'] ?? 0) - ($lower['max_fps'] ?? 0)) * $progress;
            $sourceSettings = ($lower['settings'] ?? null) === ($upper['settings'] ?? null)
                ? ($lower['settings'] ?? null)
                : ($progress < 0.5 ? ($lower['settings'] ?? null) : ($upper['settings'] ?? null));

            return [
                'avg_fps' => (int) clampValue(round($avg), 10, 999),
                'min_fps' => (int) clampValue(round(min($min, $avg - 1)), 1, 950),
                'max_fps' => (int) clampValue(round(max($max, $avg + 1)), 1, 999),
                'estimated' => true,
                'source_type' => 'benchmark-interpolated',
                'source_settings' => $sourceSettings
            ];
        }
    }

    return null;
}

try {
    // Try to get real benchmark data
    $stmt = $pdo->prepare("
        SELECT avg_fps, min_fps, max_fps, settings
        FROM benchmarks 
        WHERE component_id = ? AND game_id = ? AND resolution = ?
    ");
    $stmt->execute([$componentId, $gameId, $resolution]);
    $benchmark = $stmt->fetch();
    
    if ($benchmark) {
        $benchmark['estimated'] = false;
        $benchmark['source_type'] = 'benchmark';
        $benchmark['source_settings'] = $benchmark['settings'] ?? null;
        echo json_encode($benchmark);
    } else {
        // Estimate FPS based on performance score (check GPU table)
        $stmt = $pdo->prepare("SELECT performance_score FROM components_gpu WHERE id = ?");
        $stmt->execute([$componentId]);
        $component = $stmt->fetch();
        
        if ($component) {
            $baseScore = (int) $component['performance_score'];

            $stmt = $pdo->prepare("
                SELECT b.avg_fps, b.min_fps, b.max_fps, b.settings, g.performance_score
                FROM benchmarks b
                INNER JOIN components_gpu g ON g.id = b.component_id
                WHERE b.game_id = ? AND b.resolution = ?
                ORDER BY g.performance_score ASC
            ");
            $stmt->execute([$gameId, $resolution]);
            $benchmarkPool = $stmt->fetchAll();

            $poolEstimate = estimateFromBenchmarkPool($baseScore, $benchmarkPool);
            if ($poolEstimate) {
                echo json_encode($poolEstimate);
                exit;
            }

            // Эмпирические множители по разрешениям (на основе усреднённых тестов TechPowerUp/HardwareUnboxed)
            $resolutionMultipliers = [
                '1920x1080' => 1.00, // базовая точка (ультра-пресет)
                '2560x1440' => 0.72,
                '3840x2160' => 0.47,
                '5120x1440' => 0.45
            ];

            // Профили игр: < 1 — требовательные проекты, > 1 — киберспортивные тайтлы
            $gameMultipliers = [
                '1' => 0.62,  // Cyberpunk 2077
                '2' => 0.68,  // Red Dead Redemption 2
                '3' => 0.79,  // Elden Ring
                '4' => 2.10,  // CS2 (Counter-Strike 2)
                '5' => 0.53,  // Starfield
                '6' => 0.81   // Baldur's Gate 3
            ];

            $resolutionMultiplier = $resolutionMultipliers[$resolution] ?? 1.0;
            $gameMultiplier = $gameMultipliers[$gameId] ?? 1.0;

            // Плавно интерполируем FPS относительно производительности видеокарты
            $anchors = [
                ['score' => 3500, 'fps' => 28],   // GTX 1060 / RX 580
                ['score' => 4000, 'fps' => 35],   // GTX 1660
                ['score' => 5000, 'fps' => 55],   // RTX 2060 / RX 5600 XT
                ['score' => 6000, 'fps' => 78],   // RTX 3060 / RX 6600
                ['score' => 7000, 'fps' => 105],  // RTX 3060 Ti / RX 6700 XT
                ['score' => 8000, 'fps' => 132],  // RTX 4070 / RX 7800 XT
                ['score' => 9000, 'fps' => 160],  // RTX 4070 Ti / RX 7900 GRE
                ['score' => 10000, 'fps' => 188], // RTX 4080 / RX 7900 XT
                ['score' => 11000, 'fps' => 215], // RTX 4090
                ['score' => 12000, 'fps' => 240]  // RTX 5090 и выше
            ];

            $baseFps = interpolateFps($baseScore, $anchors);

            // Применяем профили игры/разрешения
            $estimatedFps = round($baseFps * $resolutionMultiplier * $gameMultiplier);
            $estimatedFps = max(min($estimatedFps, 999), 10);

            echo json_encode([
                'avg_fps' => $estimatedFps,
                'min_fps' => round($estimatedFps * 0.85),
                'max_fps' => round($estimatedFps * 1.10),
                'estimated' => true,
                'source_type' => 'anchor-estimate',
                'source_settings' => 'Ultra'
            ]);
        } else {
            echo json_encode(['error' => 'Component not found']);
        }
    }
} catch (PDOException $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>
