<?php
declare(strict_types=1);

final class ScoringTypeDetector
{
    public const SELECTABLE_TYPES = [
        'SCORE', 'SET', 'ROUND', 'TIME', 'DISTANCE', 'HEIGHT',
        'JUDGED', 'TARGET', 'COUNT', 'LOWEST', 'DECISION', 'COMBINED',
    ];

    public static function detect(array $sport, array $rule = []): string
    {
        $configured = strtoupper(trim((string) ($rule['scoring_type'] ?? '')));
        $createdBy = trim((string) ($rule['created_by'] ?? ''));
        if (in_array($configured, ScoringEngine::TYPES, true) && ($configured !== 'SCORE' || ($createdBy !== '' && $createdBy !== 'migration'))) {
            return $configured;
        }

        $text = mb_strtolower(trim(implode(' ', [
            (string) ($sport['icon'] ?? ''),
            (string) ($sport['name'] ?? $sport['sport_name'] ?? ''),
            (string) ($sport['type'] ?? $sport['sport_type'] ?? ''),
        ])));
        $families = [
            'HEIGHT' => ['กระโดดสูง', 'ค้ำถ่อ', 'high jump', 'pole vault'],
            'DISTANCE' => ['กระโดดไกล', 'เขย่ง', 'ทุ่มน้ำหนัก', 'ขว้างจักร', 'ขว้างหอก', 'long jump', 'shot put', 'discus', 'javelin'],
            'POINT_RANKING' => ['ยิมนาสติก', 'เชียร์ลีดดิ้ง', 'เต้น', 'gymnastic', 'cheer', 'dance'],
            'COMBAT' => ['มวย', 'เทควันโด', 'คาราเต้', 'boxing', 'muay', 'taekwondo', 'karate'],
            'STROKE' => ['กอล์ฟ', 'golf'],
            'MAP' => ['esport', 'e-sport', 'อีสปอร์ต', 'กีฬาอิเล็กทรอนิกส์'],
            'END_SCORE' => ['petanque', 'pétanque', 'เปตอง'],
            'PERIOD_SCORE' => ['basketball', 'บาสเกตบอล', 'american football'],
            'SET' => ['volleyball', 'badminton', 'table-tennis', 'table tennis', 'tennis', 'sepak', 'วอลเลย์บอล', 'แบดมินตัน', 'เทเบิลเทนนิส', 'ปิงปอง', 'เทนนิส', 'ตะกร้อ'],
            'RACE_POSITION' => ['running', 'swimming', 'cycling', 'วิ่ง', 'ว่ายน้ำ', 'จักรยาน', 'ผลัด', 'race'],
        ];
        foreach ($families as $type => $terms) {
            foreach ($terms as $term) {
                if (str_contains($text, $term)) {
                    return $type;
                }
            }
        }
        return $configured && in_array($configured, ScoringEngine::TYPES, true) ? $configured : 'SCORE';
    }

    public static function defaultConfig(string $type): array
    {
        return match (strtoupper($type)) {
            'SCORE' => ['allow_draw' => true, 'allow_extra_time' => false, 'allow_penalty' => false, 'include_in_standings' => true],
            'PERIOD_SCORE' => ['periods' => 4, 'allow_overtime' => true, 'include_in_standings' => true],
            'SET' => ['best_of' => 3, 'points_to_win_set' => 21, 'win_by' => 2, 'max_points' => 30, 'include_in_standings' => true],
            'ROUND' => ['target_score' => 0, 'include_in_standings' => true],
            'END_SCORE' => ['target_score' => 13, 'include_in_standings' => true],
            'JUDGED', 'TARGET' => ['aggregation' => 'SUM', 'include_in_standings' => false],
            'COUNT' => ['win_by' => 'HIGHEST', 'include_in_standings' => false],
            'LOWEST' => ['include_in_standings' => false],
            'DECISION' => ['include_in_standings' => true],
            'COMBINED' => ['win_by' => 'HIGHEST', 'include_in_standings' => false],
            'POINT_RANKING' => ['aggregation' => 'SUM', 'include_in_standings' => false],
            'TIME', 'RACE_POSITION' => ['rank_points' => [5, 3, 1], 'include_in_standings' => false],
            'MAP' => ['best_of' => 3, 'include_in_standings' => true],
            'COMBAT' => ['include_in_standings' => true],
            default => ['include_in_standings' => false],
        };
    }
}
