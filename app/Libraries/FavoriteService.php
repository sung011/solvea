<?php

namespace App\Libraries;

use Throwable;

class FavoriteService
{
    private SupabaseClient $supabase;

    public function __construct(?SupabaseClient $supabase = null)
    {
        $this->supabase = $supabase ?: new SupabaseClient();
    }

    public function list(string $userId): array
    {
        try {
            $rows = $this->supabase->select('m_favorite', [
                'select'  => 'kind,job_key,job_title,job_area,job_link',
                'user_id' => 'eq.' . $userId,
                'order'   => 'created_at.desc',
            ]);

            if ($this->isError($rows)) {
                return [];
            }

            return array_map(static fn (array $row): array => [
                'kind'   => (string) ($row['kind'] ?? ''),
                'jobKey' => (string) ($row['job_key'] ?? ''),
                'jobTitle' => (string) ($row['job_title'] ?? ''),
                'jobArea' => (string) ($row['job_area'] ?? ''),
                'jobLink' => (string) ($row['job_link'] ?? ''),
            ], $rows);
        } catch (Throwable $e) {
            log_message('error', 'FavoriteService list: ' . $e->getMessage());
            return [];
        }
    }

    public function save(string $userId, array $data): array
    {
        $kind = trim((string) ($data['kind'] ?? ''));
        $jobKey = trim((string) ($data['jobKey'] ?? ''));
        if ($userId === '' || $kind === '' || $jobKey === '') {
            return ['ok' => false, 'detail' => '관심 공고 정보가 올바르지 않습니다.'];
        }

        try {
            $existing = $this->supabase->select('m_favorite', [
                'select'  => 'id',
                'user_id' => 'eq.' . $userId,
                'kind'    => 'eq.' . $kind,
                'job_key' => 'eq.' . $jobKey,
                'limit'   => 1,
            ]);
            if ($this->isError($existing)) {
                return ['ok' => false, 'detail' => '관심 공고를 확인하지 못했습니다.'];
            }
            if ($existing !== []) {
                return ['ok' => true, 'saved' => true];
            }

            $row = $this->supabase->insert('m_favorite', [
                'user_id'  => $userId,
                'kind'     => $kind,
                'job_key'  => $jobKey,
                'job_title'=> trim((string) ($data['jobTitle'] ?? '')) ?: null,
                'job_area' => trim((string) ($data['jobArea'] ?? '')) ?: null,
                'job_link' => trim((string) ($data['jobLink'] ?? '')) ?: null,
            ]);

            return $this->isError($row) || $row === null
                ? ['ok' => false, 'detail' => '관심 공고 저장에 실패했습니다.']
                : ['ok' => true, 'saved' => true];
        } catch (Throwable $e) {
            log_message('error', 'FavoriteService save: ' . $e->getMessage());
            return ['ok' => false, 'detail' => '관심 공고 저장 중 오류가 발생했습니다.'];
        }
    }

    public function remove(string $userId, string $kind, string $jobKey): array
    {
        if ($userId === '' || trim($kind) === '' || trim($jobKey) === '') {
            return ['ok' => false, 'detail' => '관심 공고 정보가 올바르지 않습니다.'];
        }

        try {
            $ok = $this->supabase->delete('m_favorite', [
                'user_id' => 'eq.' . $userId,
                'kind'    => 'eq.' . trim($kind),
                'job_key' => 'eq.' . trim($jobKey),
            ]);
            return $ok
                ? ['ok' => true, 'saved' => false]
                : ['ok' => false, 'detail' => '관심 공고 해제에 실패했습니다.'];
        } catch (Throwable $e) {
            log_message('error', 'FavoriteService remove: ' . $e->getMessage());
            return ['ok' => false, 'detail' => '관심 공고 해제 중 오류가 발생했습니다.'];
        }
    }

    private function isError(mixed $response): bool
    {
        return is_array($response) && (($response['_error'] ?? false) === true);
    }
}
