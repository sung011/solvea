<?php

namespace App\Libraries;

use Throwable;

/**
 * 회원 가입/로그인 — Supabase m_user 테이블
 */
class AuthService
{
    private SupabaseClient $supabase;

    public function __construct(?SupabaseClient $supabase = null)
    {
        $this->supabase = $supabase ?: new SupabaseClient();
    }

    public function signup(array $data): array
    {
        if (!$this->supabase->isConfigured()) {
            return $this->fail('Supabase 설정이 없습니다.');
        }

        $userId = trim((string)($data['user_id'] ?? ''));
        $password = (string)($data['password'] ?? '');
        $name = trim((string)($data['name'] ?? ''));
        $address = trim((string)($data['address'] ?? ''));
        $age = $data['age'] ?? null;

        if (mb_strlen($userId) < 2 || mb_strlen($userId) > 20) {
            return $this->fail('아이디는 2~20자로 입력해 주세요.');
        }
        if (mb_strlen($password) < 4) {
            return $this->fail('비밀번호는 4자 이상이어야 합니다.');
        }
        if ($age !== null && $age !== '') {
            $age = (int)$age;
            if ($age < 1 || $age > 120) {
                return $this->fail('나이를 확인해 주세요.');
            }
        } else {
            $age = null;
        }

        try {
            $exists = $this->supabase->select('m_user', [
                'select' => 'user_id',
                'user_id' => 'eq.' . $userId,
                'limit' => 1,
            ]);
            if ($this->isError($exists)) {
                return $this->fail('회원 조회에 실패했습니다.');
            }
            if ($exists !== []) {
                return $this->fail('이미 사용 중인 아이디입니다.');
            }

            $row = $this->supabase->insert('m_user', [
                'user_id' => $userId,
                'password' => password_hash($password, PASSWORD_BCRYPT),
                'name' => $name !== '' ? $name : null,
                'age' => $age,
                'address' => $address !== '' ? $address : null,
            ]);

            if ($this->isError($row) || $row === null) {
                $detail = is_array($row) ? ($row['_body']['message'] ?? '') : '';
                if (stripos((string)$detail, 'duplicate') !== false) {
                    return $this->fail('이미 사용 중인 아이디입니다.');
                }

                return $this->fail('회원가입에 실패했습니다.');
            }

            return [
                'ok' => true,
                'user_id' => $userId,
                'name' => $name !== '' ? $name : null,
                'age' => $age,
                'address' => $address !== '' ? $address : null,
            ];
        } catch (Throwable $e) {
            log_message('error', 'AuthService signup: ' . $e->getMessage());

            return $this->fail('회원가입 처리 중 오류가 발생했습니다.');
        }
    }

    public function login(string $userId, string $password): array
    {
        if (!$this->supabase->isConfigured()) {
            return $this->fail('Supabase 설정이 없습니다.');
        }

        $userId = trim($userId);
        if ($userId === '' || $password === '') {
            return $this->fail('아이디와 비밀번호를 입력해 주세요.');
        }

        try {
            $rows = $this->supabase->select('m_user', [
                'select' => 'user_id,password,name,age,address',
                'user_id' => 'eq.' . $userId,
                'limit' => 1,
            ]);
            if ($this->isError($rows) || $rows === []) {
                return $this->fail('아이디 또는 비밀번호가 올바르지 않습니다.');
            }

            $row = $rows[0];
            $hash = (string)($row['password'] ?? '');
            if ($hash === '' || !password_verify($password, $hash)) {
                return $this->fail('아이디 또는 비밀번호가 올바르지 않습니다.');
            }

            $this->supabase->update('m_user', [
                'last_login_at' => gmdate('c'),
            ], [
                'user_id' => 'eq.' . $userId,
            ]);

            return [
                'ok' => true,
                'user_id' => (string)$row['user_id'],
                'name' => $row['name'] ?? null,
                'age' => isset($row['age']) && $row['age'] !== null ? (int)$row['age'] : null,
                'address' => $row['address'] ?? null,
            ];
        } catch (Throwable $e) {
            log_message('error', 'AuthService login: ' . $e->getMessage());

            return $this->fail('로그인 처리 중 오류가 발생했습니다.');
        }
    }

    private function fail(string $message): array
    {
        return ['ok' => false, 'detail' => $message];
    }

    private function isError(mixed $response): bool
    {
        return is_array($response) && (($response['_error'] ?? false) === true);
    }
}
