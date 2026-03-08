<?php

namespace Tests\Unit;

use App\Core\Domain\Exceptions\InvalidEmailException;
use App\Core\Domain\ValueObjects\Email;
use App\Infrastructure\Persistence\Eloquent\Casts\EmailCast;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Database\Factories\UserModelFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class EmailCastTest extends TestCase
{
    use RefreshDatabase;

    public function test_get_returns_email_value_object_from_string(): void
    {
        $cast = new EmailCast;
        $model = new UserModel;

        $result = $cast->get($model, 'email', 'test@example.com', [
            'email' => 'test@example.com',
        ]);

        $this->assertInstanceOf(Email::class, $result);
        $this->assertEquals('test@example.com', $result->getValue());
    }

    public function test_get_returns_null_when_value_is_null(): void
    {
        $cast = new EmailCast;
        $model = new UserModel;

        $result = $cast->get($model, 'email', null, ['email' => null]);

        $this->assertNull($result);
    }

    public function test_get_throws_exception_for_invalid_email(): void
    {
        $this->expectException(InvalidEmailException::class);

        $cast = new EmailCast;
        $model = new UserModel;

        $cast->get($model, 'email', 'invalid-email', ['email' => 'invalid-email']);
    }

    public function test_set_returns_string_from_email_value_object(): void
    {
        $cast = new EmailCast;
        $model = new UserModel;
        $email = new Email('test@example.com');

        $result = $cast->set($model, 'email', $email, [
            'email' => 'test@example.com',
        ]);

        $this->assertEquals('test@example.com', $result);
    }

    public function test_set_returns_null_when_value_is_null(): void
    {
        $cast = new EmailCast;
        $model = new UserModel;

        $result = $cast->set($model, 'email', null, ['email' => null]);

        $this->assertNull($result);
    }

    public function test_set_accepts_string_directly(): void
    {
        $cast = new EmailCast;
        $model = new UserModel;

        $result = $cast->set($model, 'email', 'test@example.com', [
            'email' => 'test@example.com',
        ]);

        $this->assertEquals('test@example.com', $result);
    }

    public function test_set_throws_exception_for_invalid_type(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The given value is not an Email instance.');

        $cast = new EmailCast;
        $model = new UserModel;

        $cast->set($model, 'email', ['invalid' => 'type'], []);
    }

    public function test_user_model_automatically_casts_email_on_retrieval(): void
    {
        $user = UserModelFactory::new()->create([
            'email' => 'auto@example.com',
        ]);

        $retrieved = UserModel::find($user->id);

        $this->assertInstanceOf(Email::class, $retrieved->email);
        $this->assertEquals('auto@example.com', $retrieved->email->getValue());
    }

    public function test_user_model_accepts_email_value_object_on_save(): void
    {
        $email = new Email('valueobject@example.com');

        $user = UserModelFactory::new()->create([
            'email' => $email,
        ]);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'email' => 'valueobject@example.com',
        ]);

        $retrieved = UserModel::find($user->id);
        $this->assertInstanceOf(Email::class, $retrieved->email);
        $this->assertEquals(
            'valueobject@example.com',
            $retrieved->email->getValue(),
        );
    }

    public function test_user_model_accepts_string_email_on_save(): void
    {
        $user = UserModelFactory::new()->create([
            'email' => 'string@example.com',
        ]);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'email' => 'string@example.com',
        ]);

        $retrieved = UserModel::find($user->id);
        $this->assertInstanceOf(Email::class, $retrieved->email);
        $this->assertEquals('string@example.com', $retrieved->email->getValue());
    }

    public function test_user_model_email_can_be_updated_with_value_object(): void
    {
        $user = UserModelFactory::new()->create([
            'email' => 'original@example.com',
        ]);

        $user->email = new Email('updated@example.com');
        $user->save();

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'email' => 'updated@example.com',
        ]);
    }

    public function test_user_model_email_equals_comparison_works(): void
    {
        $user = UserModelFactory::new()->create([
            'email' => 'compare@example.com',
        ]);

        $retrieved = UserModel::find($user->id);

        $this->assertTrue(
            $retrieved->email->equals(new Email('compare@example.com')),
        );
        $this->assertFalse(
            $retrieved->email->equals(new Email('different@example.com')),
        );
    }

    public function test_user_model_email_to_string_works(): void
    {
        $user = UserModelFactory::new()->create([
            'email' => 'tostring@example.com',
        ]);

        $retrieved = UserModel::find($user->id);

        $this->assertEquals('tostring@example.com', (string) $retrieved->email);
    }
}
