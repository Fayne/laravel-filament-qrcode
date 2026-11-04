<?php

namespace Fayne\QrCode\Rules;

use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Fayne\QrCode\Support\Generator;

class ValidMiniQrcode implements DataAwareRule, ValidationRule
{
    /**
     * @var array
     */
    protected array $data = [];

    /**
     * Run the validation rule.
     *
     * @param string $attribute
     * @param mixed $value
     * @param Closure $fail
     * @return void
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // 确保 scene 参数存在
        if (!isset($this->data['scene'])) {
            $fail('The scene parameter is required.');

            return;
        }

        // 验证路径格式
        if (!$this->isValidPath($value)) {
            $fail('The page path format is invalid.');

            return;
        }

        try {
            Generator::getQrcodeOutput(
                scene: $this->data['scene'],
                page: $value,
                checkPath: true
            );
        } catch (\Throwable $exception) {
            $fail($exception);
        }
    }

    /**
     * Get the validation error message.
     */
    public static function message(): string
    {
        return __('Mini App Qrcode is invalid.');
    }

    /**
     * Set the data under validation.
     *
     * @param array $data
     * @return $this
     */
    public function setData(array $data)
    {
        $this->data = $data;

        return $this;
    }

    /**
     * @param string $value
     * @return bool
     */
    protected function isValidPath(string $value): bool
    {
        // 不能是空字符串
        if (empty($value)) {
            return false;
        }

        // 不能以"/"开头
        if (str_starts_with($value, '/')) {
            return false;
        }

        // 不能以"["开头
        if (str_starts_with($value, '[')) {
            return false;
        }

        // 不能包含空数组表达式 "[]"
        if (str_contains($value, '[]')) {
            return false;
        }

        // 检查是否存在未闭合的数组表达式
        $firstOpenBracket = strpos($value, '[');
        $firstCloseBracket = strpos($value, ']');

        if ($firstCloseBracket !== false && ($firstOpenBracket === false || $firstCloseBracket < $firstOpenBracket)) {
            return false;
        }

        // 检查是否以未闭合的数组表达式结尾
        if (preg_match('/\[[^\]]*$/', $value)) {
            return false;
        }

        return true;
    }
}
