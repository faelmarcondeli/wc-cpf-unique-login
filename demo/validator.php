<?php

class DocumentValidator {

    public function validate(string $value, string $type): array {
        $normalized = preg_replace('/[^0-9]/', '', $value);

        if (empty($normalized)) {
            return ['valid' => false, 'message' => 'Please enter a document number.'];
        }

        if ($type === 'cpf') {
            return $this->validateCPF($normalized);
        }

        return $this->validateCNPJ($normalized);
    }

    private function validateCPF(string $cpf): array {
        if (strlen($cpf) !== 11) {
            return ['valid' => false, 'message' => 'CPF must have 11 digits. You entered ' . strlen($cpf) . ' digits.'];
        }

        if (preg_match('/^(\d)\1{10}$/', $cpf)) {
            return ['valid' => false, 'message' => 'Invalid CPF: all digits are the same.'];
        }

        for ($t = 9; $t < 11; $t++) {
            $sum = 0;
            for ($i = 0; $i < $t; $i++) {
                $sum += $cpf[$i] * (($t + 1) - $i);
            }
            $digit = ((10 * $sum) % 11) % 10;
            if ((int)$cpf[$t] !== $digit) {
                return ['valid' => false, 'message' => 'Invalid CPF: check digit verification failed.'];
            }
        }

        $formatted = substr($cpf, 0, 3) . '.' . substr($cpf, 3, 3) . '.' . substr($cpf, 6, 3) . '-' . substr($cpf, 9, 2);
        return ['valid' => true, 'message' => "Valid CPF: $formatted"];
    }

    private function validateCNPJ(string $cnpj): array {
        if (strlen($cnpj) !== 14) {
            return ['valid' => false, 'message' => 'CNPJ must have 14 digits. You entered ' . strlen($cnpj) . ' digits.'];
        }

        if (preg_match('/^(\d)\1{13}$/', $cnpj)) {
            return ['valid' => false, 'message' => 'Invalid CNPJ: all digits are the same.'];
        }

        $weights1 = [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];
        $weights2 = [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];

        $sum = 0;
        for ($i = 0; $i < 12; $i++) {
            $sum += $cnpj[$i] * $weights1[$i];
        }
        $digit1 = ($sum % 11 < 2) ? 0 : 11 - ($sum % 11);

        if ((int)$cnpj[12] !== $digit1) {
            return ['valid' => false, 'message' => 'Invalid CNPJ: first check digit verification failed.'];
        }

        $sum = 0;
        for ($i = 0; $i < 13; $i++) {
            $sum += $cnpj[$i] * $weights2[$i];
        }
        $digit2 = ($sum % 11 < 2) ? 0 : 11 - ($sum % 11);

        if ((int)$cnpj[13] !== $digit2) {
            return ['valid' => false, 'message' => 'Invalid CNPJ: second check digit verification failed.'];
        }

        $formatted = substr($cnpj, 0, 2) . '.' . substr($cnpj, 2, 3) . '.' . substr($cnpj, 5, 3) . '/' . substr($cnpj, 8, 4) . '-' . substr($cnpj, 12, 2);
        return ['valid' => true, 'message' => "Valid CNPJ: $formatted"];
    }
}
