<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use Throwable;

class DatabaseUpdateController extends BaseController
{
    public function index(): string
    {
        $state = $this->migrationState();

        return view('admin/system/database_update', [
            'title' => 'Database Update',
            'availableMigrations' => $state['available'],
            'appliedMigrations' => $state['applied'],
            'pendingMigrations' => $state['pending'],
            'stateError' => $state['error'],
        ]);
    }

    public function run()
    {
        try {
            $runner = service('migrations');
            $completed = $runner->latest();
            if (! $completed) {
                return redirect()->back()->with('error', 'Database update did not complete. Check the server log.');
            }

            $state = $this->migrationState();
            if ($state['pending'] !== []) {
                return redirect()->back()->with('warning', 'Database update ran, but some migrations are still pending.');
            }

            return redirect()->to(site_url('admin/system/database-update'))
                ->with('success', 'Database is up to date. All available migrations are applied.');
        } catch (Throwable $e) {
            log_message('error', 'Web database migration failed: {message}', ['message' => $e->getMessage()]);

            return redirect()->back()->with('error', 'Database update failed: ' . $e->getMessage());
        }
    }

    /**
     * @return array{available:list<array<string,mixed>>,applied:list<array<string,mixed>>,pending:list<array<string,mixed>>,error:string}
     */
    private function migrationState(): array
    {
        try {
            $runner = service('migrations');
            $availableObjects = $runner->findMigrations();
            $historyObjects = $runner->getHistory('default');

            $historyKeys = [];
            $applied = [];
            foreach ($historyObjects as $history) {
                $namespace = (string) ($history->namespace ?? 'App');
                $version = (string) ($history->version ?? '');
                $class = (string) ($history->class ?? '');
                $historyKeys[$namespace . '|' . $version . '|' . $class] = true;
                $applied[] = [
                    'version' => $version,
                    'name' => $this->shortClassName($class),
                    'batch' => (int) ($history->batch ?? 0),
                ];
            }

            $available = [];
            $pending = [];
            foreach ($availableObjects as $migration) {
                $namespace = (string) ($migration->namespace ?? 'App');
                $version = (string) ($migration->version ?? '');
                $class = (string) ($migration->class ?? '');
                $row = [
                    'version' => $version,
                    'name' => (string) (($migration->name ?? '') ?: $this->shortClassName($class)),
                    'namespace' => $namespace,
                ];
                $available[] = $row;
                if (! isset($historyKeys[$namespace . '|' . $version . '|' . $class])) {
                    $pending[] = $row;
                }
            }

            usort($applied, static fn(array $a, array $b): int => strcmp((string) $b['version'], (string) $a['version']));

            return [
                'available' => $available,
                'applied' => $applied,
                'pending' => $pending,
                'error' => '',
            ];
        } catch (Throwable $e) {
            return [
                'available' => [],
                'applied' => [],
                'pending' => [],
                'error' => $e->getMessage(),
            ];
        }
    }

    private function shortClassName(string $class): string
    {
        $parts = explode('\\', $class);

        return (string) (end($parts) ?: $class);
    }
}
