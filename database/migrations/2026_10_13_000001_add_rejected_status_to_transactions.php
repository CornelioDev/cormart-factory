<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Estado `rejected` para transacciones.
 *
 * Un cobro que registra un company_user queda `pending` hasta que el fondo lo
 * verifica. Hasta ahora lo único que se podía hacer con él era confirmarlo: si
 * el pago no existía o estaba mal registrado, no había forma de sacarlo de la
 * cola sin tocar la base a mano.
 *
 * `rejected` lo retira sin borrarlo: queda el rastro de quién lo registró, quién
 * lo rechazó y por qué. Todas las sumas contables filtran por `confirmed`, así
 * que un rechazado no entra en ninguna.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE `transactions` MODIFY `status` ENUM('pending', 'confirmed', 'rejected') NOT NULL DEFAULT 'pending'");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            DB::table('transactions')->where('status', 'rejected')->delete();
            DB::statement("ALTER TABLE `transactions` MODIFY `status` ENUM('pending', 'confirmed') NOT NULL DEFAULT 'pending'");
        }
    }
};
