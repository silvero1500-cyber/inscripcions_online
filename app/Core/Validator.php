<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Validador minimalista.
 *
 * Uso:
 *   $v = new Validator($_POST);
 *   $v->required('titulo')->max('titulo', 255);
 *   $v->required('precio')->decimal('precio');
 *   if ($v->fails()) { ... $v->errors() ... }
 */
final class Validator
{
    /** @var array<string, list<string>> */
    private array $errors = [];

    public function __construct(private array $data) {}

    public function required(string $field, string $msg = ''): self
    {
        $v = $this->data[$field] ?? null;
        if ($v === null || (is_string($v) && trim($v) === '')) {
            $this->addError($field, $msg !== '' ? $msg : 'Aquest camp és obligatori.');
        }
        return $this;
    }

    public function max(string $field, int $length, string $msg = ''): self
    {
        $v = (string) ($this->data[$field] ?? '');
        if ($v !== '' && mb_strlen($v) > $length) {
            $this->addError($field, $msg !== '' ? $msg : "Màxim {$length} caràcters.");
        }
        return $this;
    }

    public function min(string $field, int $length, string $msg = ''): self
    {
        $v = (string) ($this->data[$field] ?? '');
        if ($v !== '' && mb_strlen($v) < $length) {
            $this->addError($field, $msg !== '' ? $msg : "Mínim {$length} caràcters.");
        }
        return $this;
    }

    public function email(string $field, string $msg = ''): self
    {
        $v = (string) ($this->data[$field] ?? '');
        if ($v !== '' && !filter_var($v, FILTER_VALIDATE_EMAIL)) {
            $this->addError($field, $msg !== '' ? $msg : 'Email no vàlid.');
        }
        return $this;
    }

    public function date(string $field, string $msg = ''): self
    {
        $v = (string) ($this->data[$field] ?? '');
        if ($v !== '') {
            $d = \DateTimeImmutable::createFromFormat('Y-m-d', $v);
            if ($d === false || $d->format('Y-m-d') !== $v) {
                $this->addError($field, $msg !== '' ? $msg : 'Data no vàlida.');
            }
        }
        return $this;
    }

    public function dateTime(string $field, string $msg = ''): self
    {
        $v = (string) ($this->data[$field] ?? '');
        if ($v !== '') {
            // Acepta formato YYYY-MM-DDTHH:MM o YYYY-MM-DD HH:MM(:SS)
            $v = str_replace('T', ' ', $v);
            $formats = ['Y-m-d H:i', 'Y-m-d H:i:s'];
            $ok = false;
            foreach ($formats as $f) {
                $d = \DateTimeImmutable::createFromFormat($f, $v);
                if ($d !== false && $d->format($f) === $v) { $ok = true; break; }
            }
            if (!$ok) {
                $this->addError($field, $msg !== '' ? $msg : 'Data i hora no vàlides.');
            }
        }
        return $this;
    }

    public function decimal(string $field, string $msg = ''): self
    {
        $v = (string) ($this->data[$field] ?? '');
        if ($v !== '' && !preg_match('/^\d{1,6}(\.\d{1,2})?$/', $v)) {
            $this->addError($field, $msg !== '' ? $msg : 'Import no vàlid.');
        }
        return $this;
    }

    public function integer(string $field, int $min = 0, ?int $max = null, string $msg = ''): self
    {
        $v = $this->data[$field] ?? null;
        if ($v === null || $v === '') return $this;
        if (!is_numeric($v) || (int)$v != $v || (int)$v < $min || ($max !== null && (int)$v > $max)) {
            $this->addError($field, $msg !== '' ? $msg : 'Número no vàlid.');
        }
        return $this;
    }

    public function in(string $field, array $allowed, string $msg = ''): self
    {
        $v = $this->data[$field] ?? null;
        if ($v !== null && $v !== '' && !in_array($v, $allowed, true)) {
            $this->addError($field, $msg !== '' ? $msg : 'Valor no vàlid.');
        }
        return $this;
    }

    public function addError(string $field, string $message): void
    {
        $this->errors[$field][] = $message;
    }

    public function fails(): bool
    {
        return count($this->errors) > 0;
    }

    public function passes(): bool
    {
        return !$this->fails();
    }

    /** @return array<string, list<string>> */
    public function errors(): array
    {
        return $this->errors;
    }

    public function first(string $field): ?string
    {
        return $this->errors[$field][0] ?? null;
    }
}
