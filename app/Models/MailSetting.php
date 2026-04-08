<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class MailSetting extends Model
{
    protected $fillable = [
        'transport',
        'host',
        'port',
        'username',
        'password',
        'encryption',
        'from_address',
        'from_name',
    ];

    protected $hidden = ['password'];

    /**
     * Retorna la instancia singleton (primer registro) o crea una con defaults.
     */
    public static function instance(): static
    {
        return static::firstOrCreate([], [
            'transport'    => 'smtp',
            'host'         => config('mail.mailers.smtp.host'),
            'port'         => config('mail.mailers.smtp.port'),
            'username'     => config('mail.mailers.smtp.username'),
            'encryption'   => config('mail.mailers.smtp.scheme') ?? 'smtps',
            'from_address' => config('mail.from.address'),
            'from_name'    => config('mail.from.name'),
        ]);
    }

    /**
     * Encripta el password antes de guardar.
     */
    public function setPasswordAttribute(?string $value): void
    {
        $this->attributes['password'] = $value ? Crypt::encryptString($value) : null;
    }

    /**
     * Desencripta el password al leer.
     */
    public function getDecryptedPasswordAttribute(): ?string
    {
        if (! $this->attributes['password']) {
            return null;
        }

        try {
            return Crypt::decryptString($this->attributes['password']);
        } catch (\Exception) {
            return null;
        }
    }
}
