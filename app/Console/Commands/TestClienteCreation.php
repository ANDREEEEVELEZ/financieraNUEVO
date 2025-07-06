<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Persona;
use App\Models\Cliente;

class TestClienteCreation extends Command
{
    protected $signature = 'test:cliente-creation';
    protected $description = 'Test cliente creation process';

    public function handle()
    {
        try {
            $this->info('Testing cliente creation...');

            // Datos de prueba
            $personaData = [
                'DNI' => '12345678',
                'nombre' => 'Juan',
                'apellidos' => 'Pérez García',
                'sexo' => 'Masculino',
                'fecha_nacimiento' => '1990-01-01',
                'celular' => '987654321',
                'correo' => 'test@example.com',
                'direccion' => 'Av. Test 123',
                'distrito' => 'Sullana',
                'estado_civil' => 'Soltero'
            ];

            $clienteData = [
                'infocorp' => 'Bien Calificado',
                'ciclo' => 'I',
                'condicion_vivienda' => 'Propia',
                'actividad' => 'Comercio',
                'condicion_personal' => 'Capacitado',
                'estado_cliente' => 'Activo'
            ];

            // Crear persona
            $this->info('Creating persona...');
            $persona = Persona::create($personaData);
            $this->info('Persona created with ID: ' . $persona->id);

            // Crear cliente
            $this->info('Creating cliente...');
            $clienteData['persona_id'] = $persona->id;
            $cliente = Cliente::create($clienteData);
            $this->info('Cliente created with ID: ' . $cliente->id);

            $this->info('✅ Test completed successfully!');

        } catch (\Exception $e) {
            $this->error('❌ Error: ' . $e->getMessage());
            $this->error('File: ' . $e->getFile() . ':' . $e->getLine());
        }
    }
}
