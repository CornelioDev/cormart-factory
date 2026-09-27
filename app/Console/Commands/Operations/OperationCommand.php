<?php

namespace App\Console\Commands\Operations;

use App\Models\User;
use App\Services\LedgerVerificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Symfony\Component\Console\Input\InputOption;

/**
 * Base de los comandos de operaciones excepcionales (prefijo `ops:`).
 *
 * Son correcciones que la UI no expone a propósito porque tocan el ledger:
 * trasladar un desembolso, revertir un asiento, reencuadrar un período. Van por
 * CLI justamente para que no sean un botón que alguien pueda apretar sin pensar.
 *
 * Autorización en CLI: no hay sesión ni usuario autenticado en consola, así que
 * "solo super_admin" no puede apoyarse en `auth()`. Cada comando exige `--as`
 * con el correo de un super_admin real, verifica el rol contra la base y recién
 * entonces autentica a ese usuario para el resto de la ejecución. Eso da dos
 * cosas: la capa de servicios (que sí llama a `auth()`) funciona igual que desde
 * la UI, y queda registrado quién autorizó la operación.
 *
 * El acceso SSH sigue siendo la primera barrera; esto es la segunda, y la que
 * deja rastro.
 *
 * Qué aporta a las subclases:
 *   - `--as` y `--dry-run` sin repetirlos en cada signature
 *   - `actor()` : el super_admin que autoriza, ya autenticado
 *   - `requireBalancedLedgers()` : aborta si los ledgers ya venían descuadrados
 *   - `assertLedgersBalanced()` : dentro de la transacción, revierte si no cuadra
 *   - `logOperation()` : asiento en el log de quién hizo qué
 *
 * Una subclase implementa `perform()` y no toca `handle()`.
 */
abstract class OperationCommand extends Command
{
    private ?User $actor = null;

    protected function configure(): void
    {
        parent::configure();

        $this->addOption(
            'as',
            null,
            InputOption::VALUE_REQUIRED,
            'Correo del super_admin que autoriza la operación (obligatorio)'
        );

        $this->addOption(
            'dry-run',
            null,
            InputOption::VALUE_NONE,
            'Muestra el plan sin escribir nada'
        );
    }

    public function handle(): int
    {
        try {
            $this->actor = $this->resolveActor();
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        // Autenticar al super_admin para que la capa de servicios se comporte
        // igual que cuando la operación viene de la UI.
        Auth::login($this->actor);

        $this->line("Autorizado por {$this->actor->email} (super_admin)");

        try {
            return $this->perform();
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }

    /**
     * La operación concreta. Devuelve SUCCESS o FAILURE.
     */
    abstract protected function perform(): int;

    protected function actor(): User
    {
        if (! $this->actor) {
            throw new RuntimeException('La operación no tiene un super_admin autorizante resuelto.');
        }

        return $this->actor;
    }

    protected function isDryRun(): bool
    {
        return (bool) $this->option('dry-run');
    }

    /**
     * Resuelve y valida al super_admin que autoriza.
     */
    private function resolveActor(): User
    {
        $email = trim((string) $this->option('as'));

        if ($email === '') {
            throw new RuntimeException(
                'Falta --as con el correo del super_admin que autoriza. '
                . 'Ej: --as=admin@corneliodev.com'
            );
        }

        $user = User::where('email', $email)->first();

        if (! $user) {
            throw new RuntimeException("No existe un usuario con el correo {$email}.");
        }

        if (! $user->hasRole('super_admin')) {
            throw new RuntimeException(
                "{$email} no tiene el rol super_admin. Las operaciones `ops:` son exclusivas de super_admin."
            );
        }

        if (! $user->is_active) {
            throw new RuntimeException("El usuario {$email} está inactivo.");
        }

        return $user;
    }

    /**
     * Nunca corregir encima de un descuadre preexistente: el resultado sería
     * indistinguible del cambio y se perdería la causa original.
     */
    protected function requireBalancedLedgers(): void
    {
        $failed = (new LedgerVerificationService())->runChecks();

        if (! empty($failed)) {
            $this->renderChecks($failed);

            throw new RuntimeException('Los ledgers ya están descuadrados antes de empezar. Resuelva eso primero.');
        }
    }

    /**
     * Para llamar DENTRO de la transacción: si la operación descuadró algo,
     * la excepción revierte todo.
     */
    protected function assertLedgersBalanced(): void
    {
        $failed = (new LedgerVerificationService())->runChecks();

        if (! empty($failed)) {
            $this->renderChecks($failed);

            throw new RuntimeException('La operación dejaría los ledgers descuadrados. Se revirtió todo.');
        }
    }

    /**
     * Deja rastro permanente de quién autorizó qué, fuera de la base de datos
     * que la operación acaba de tocar.
     *
     * @param  array<string, mixed>  $context
     */
    protected function logOperation(string $summary, array $context = []): void
    {
        Log::info("[ops] {$this->getName()}: {$summary}", array_merge([
            'authorized_by' => $this->actor()->email,
            'user_id'       => $this->actor()->id,
        ], $context));
    }

    protected function money(float $value): string
    {
        return 'RD$ ' . number_format($value, 2, '.', ',');
    }

    /**
     * @param  array<int, array<string, mixed>>  $checks
     */
    protected function renderChecks(array $checks): void
    {
        foreach ($checks as $check) {
            $this->error(sprintf(
                '%s — esperado %s, actual %s, diff %s',
                $check['name'],
                number_format((float) $check['expected'], 2),
                number_format((float) $check['actual'], 2),
                number_format((float) $check['diff'], 2)
            ));
            $this->line('  ' . $check['detail']);
        }
    }
}
