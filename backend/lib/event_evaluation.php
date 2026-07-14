<?php

/**
 * Post-event evaluation questions (1 = lowest, 5 = highest).
 * Attendees only; stored as JSON on event_feedback.evaluation_json.
 */

function eventify_evaluation_sections(): array
{
    return [
        'event' => [
            'label' => 'About the event',
            'questions' => [
                [
                    'key' => 'overall_event',
                    'text' => 'Overall, how satisfied were you with this event?',
                ],
                [
                    'key' => 'event_organization',
                    'text' => 'The event was well organized (schedule, announcements, and flow).',
                ],
                [
                    'key' => 'event_activities',
                    'text' => 'The activities or sessions met my expectations.',
                ],
                [
                    'key' => 'event_venue',
                    'text' => 'The venue and facilities were adequate for the event.',
                ],
                [
                    'key' => 'event_staff',
                    'text' => 'Staff and volunteers were helpful and approachable.',
                ],
            ],
        ],
        'system' => [
            'label' => 'About EVENTIFY (compared with manual sign-up / paper forms)',
            'questions' => [
                [
                    'key' => 'system_find_info',
                    'text' => 'It was easy to find event details and updates on EVENTIFY.',
                ],
                [
                    'key' => 'system_rsvp',
                    'text' => 'Registering (RSVP) and signing up for activities on EVENTIFY was easy.',
                ],
                [
                    'key' => 'system_checkin',
                    'text' => 'QR check-in was easier and faster than manual sign-in sheets.',
                ],
                [
                    'key' => 'system_vs_manual',
                    'text' => 'Using EVENTIFY for this event was easier than doing everything manually (paper forms, walk-in lists, etc.).',
                ],
                [
                    'key' => 'system_overall',
                    'text' => 'Overall, I am satisfied with using EVENTIFY for school events.',
                ],
            ],
        ],
    ];
}

/** @return list<string> */
function eventify_evaluation_question_keys(): array
{
    $keys = [];
    foreach (eventify_evaluation_sections() as $section) {
        foreach ($section['questions'] as $q) {
            $keys[] = (string) $q['key'];
        }
    }
    return $keys;
}

/** @return array<string, string> key => question text */
function eventify_evaluation_question_labels(): array
{
    $labels = [];
    foreach (eventify_evaluation_sections() as $section) {
        foreach ($section['questions'] as $q) {
            $labels[(string) $q['key']] = (string) $q['text'];
        }
    }
    return $labels;
}

/**
 * @param array<string, mixed> $input Usually $_POST['eval']
 * @return array{scores: array<string, int>, valid: bool}
 */
function eventify_evaluation_parse_scores(array $input): array
{
    $scores = [];
    $valid = true;
    foreach (eventify_evaluation_question_keys() as $key) {
        $raw = $input[$key] ?? null;
        if ($raw === null || $raw === '') {
            $valid = false;
            continue;
        }
        $val = (int) $raw;
        if ($val < 1 || $val > 5) {
            $valid = false;
            continue;
        }
        $scores[$key] = $val;
    }
    if (count($scores) !== count(eventify_evaluation_question_keys())) {
        $valid = false;
    }
    return ['scores' => $scores, 'valid' => $valid];
}

function eventify_evaluation_ensure_schema(mysqli $conn): bool
{
    try {
        $t = $conn->query("SHOW TABLES LIKE 'event_feedback'");
        if (!$t || $t->num_rows < 1) {
            return false;
        }
        $c = $conn->query("SHOW COLUMNS FROM event_feedback LIKE 'evaluation_json'");
        if ($c && $c->num_rows > 0) {
            return true;
        }
        $conn->query(
            "ALTER TABLE event_feedback ADD COLUMN evaluation_json JSON NULL DEFAULT NULL AFTER comment"
        );
        return true;
    } catch (Throwable $e) {
        return false;
    }
}

/**
 * @param list<array<string, mixed>> $rows Rows with evaluation_json
 * @return array<string, array{avg: float|null, count: int}>
 */
function eventify_evaluation_aggregate_from_rows(array $rows): array
{
    $sums = [];
    $counts = [];
    foreach (eventify_evaluation_question_keys() as $key) {
        $sums[$key] = 0;
        $counts[$key] = 0;
    }
    foreach ($rows as $row) {
        $raw = $row['evaluation_json'] ?? null;
        if ($raw === null || $raw === '') {
            continue;
        }
        $data = is_array($raw) ? $raw : json_decode((string) $raw, true);
        if (!is_array($data)) {
            continue;
        }
        foreach ($data as $key => $val) {
            if (!array_key_exists($key, $sums)) {
                continue;
            }
            $rating = (int) $val;
            if ($rating < 1 || $rating > 5) {
                continue;
            }
            $sums[$key] += $rating;
            $counts[$key]++;
        }
    }
    $out = [];
    foreach ($sums as $key => $sum) {
        $cnt = $counts[$key];
        $out[$key] = [
            'avg' => $cnt > 0 ? round($sum / $cnt, 2) : null,
            'count' => $cnt,
        ];
    }
    return $out;
}
