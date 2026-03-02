<?php

declare(strict_types=1);

namespace App\Tests\Unit\Helper;

use App\Helper\ValidatorHelper;
use PHPUnit\Framework\TestCase;

class ValidatorHelperTest extends TestCase
{
    private ValidatorHelper $validator;

    protected function setUp(): void
    {
        $this->validator = new ValidatorHelper();
    }

    public function testRequiredPassesWithValue(): void
    {
        $this->assertTrue($this->validator->required('hello', 'name'));
        $this->assertFalse($this->validator->hasErrors());
    }

    public function testRequiredFailsWithNull(): void
    {
        $this->assertFalse($this->validator->required(null, 'name'));
        $this->assertTrue($this->validator->hasErrors());
        $errors = $this->validator->getErrors();
        $this->assertArrayHasKey('name', $errors);
    }

    public function testRequiredFailsWithEmptyString(): void
    {
        $this->assertFalse($this->validator->required('', 'name'));
        $this->assertTrue($this->validator->hasErrors());
    }

    public function testRequiredFailsWithEmptyArray(): void
    {
        $this->assertFalse($this->validator->required([], 'items'));
        $this->assertTrue($this->validator->hasErrors());
    }

    public function testStringLengthPasses(): void
    {
        $this->assertTrue($this->validator->stringLength('hello', 'name', 1, 255));
        $this->assertFalse($this->validator->hasErrors());
    }

    public function testStringLengthFailsTooShort(): void
    {
        $this->assertFalse($this->validator->stringLength('', 'name', 1, 255));
        $this->assertTrue($this->validator->hasErrors());
    }

    public function testStringLengthFailsTooLong(): void
    {
        $this->assertFalse($this->validator->stringLength('abcdef', 'name', 1, 3));
        $this->assertTrue($this->validator->hasErrors());
    }

    public function testIntegerPassesWithInt(): void
    {
        $this->assertTrue($this->validator->integer(42, 'age'));
    }

    public function testIntegerPassesWithNumericString(): void
    {
        $this->assertTrue($this->validator->integer('42', 'age'));
    }

    public function testIntegerFailsWithFloat(): void
    {
        $this->assertFalse($this->validator->integer(3.14, 'age'));
    }

    public function testIntegerFailsWithString(): void
    {
        $this->assertFalse($this->validator->integer('abc', 'age'));
    }

    public function testIntegerRangePasses(): void
    {
        $this->assertTrue($this->validator->integerRange(25, 'age', 0, 150));
    }

    public function testIntegerRangeFailsBelowMin(): void
    {
        $this->assertFalse($this->validator->integerRange(-1, 'age', 0, 150));
    }

    public function testIntegerRangeFailsAboveMax(): void
    {
        $this->assertFalse($this->validator->integerRange(200, 'age', 0, 150));
    }

    public function testEmailPasses(): void
    {
        $this->assertTrue($this->validator->email('user@example.com', 'email'));
    }

    public function testEmailFails(): void
    {
        $this->assertFalse($this->validator->email('not-an-email', 'email'));
    }

    public function testUrlPasses(): void
    {
        $this->assertTrue($this->validator->url('https://example.com', 'website'));
    }

    public function testUrlFails(): void
    {
        $this->assertFalse($this->validator->url('not-a-url', 'website'));
    }

    public function testIsArrayPasses(): void
    {
        $this->assertTrue($this->validator->isArray([1, 2, 3], 'items'));
    }

    public function testIsArrayFails(): void
    {
        $this->assertFalse($this->validator->isArray('not-array', 'items'));
    }

    public function testInPasses(): void
    {
        $this->assertTrue($this->validator->in('active', 'status', ['active', 'inactive']));
    }

    public function testInFails(): void
    {
        $this->assertFalse($this->validator->in('deleted', 'status', ['active', 'inactive']));
    }

    public function testRegexPasses(): void
    {
        $this->assertTrue($this->validator->regex('abc123', 'code', '/^[a-z0-9]+$/'));
    }

    public function testRegexFails(): void
    {
        $this->assertFalse($this->validator->regex('ABC!', 'code', '/^[a-z0-9]+$/'));
    }

    public function testValidateWithRules(): void
    {
        $data = [
            'name' => 'Test School',
            'email' => 'admin@school.com',
        ];

        $rules = [
            'name' => ['required', ['stringLength', 1, 255]],
            'email' => ['required', 'email'],
        ];

        $this->assertTrue($this->validator->validate($data, $rules));
        $this->assertFalse($this->validator->hasErrors());
    }

    public function testValidateFailsWithInvalidData(): void
    {
        $data = [
            'name' => '',
            'email' => 'not-email',
        ];

        $rules = [
            'name' => ['required'],
            'email' => ['email'],
        ];

        $this->assertFalse($this->validator->validate($data, $rules));
        $this->assertTrue($this->validator->hasErrors());
        $errors = $this->validator->getErrors();
        $this->assertArrayHasKey('name', $errors);
        $this->assertArrayHasKey('email', $errors);
    }

    public function testClearErrors(): void
    {
        $this->validator->required(null, 'field');
        $this->assertTrue($this->validator->hasErrors());

        $this->validator->clearErrors();
        $this->assertFalse($this->validator->hasErrors());
        $this->assertEmpty($this->validator->getErrors());
    }

    public function testCustomValidation(): void
    {
        $result = $this->validator->custom(10, 'value', function ($v) {
            return $v > 5 ? true : 'Value must be greater than 5';
        });
        $this->assertTrue($result);
    }

    public function testCustomValidationFails(): void
    {
        $result = $this->validator->custom(3, 'value', function ($v) {
            return $v > 5 ? true : 'Value must be greater than 5';
        });
        $this->assertFalse($result);
        $errors = $this->validator->getErrors();
        $this->assertStringContainsString('greater than 5', $errors['value'][0]);
    }
}
