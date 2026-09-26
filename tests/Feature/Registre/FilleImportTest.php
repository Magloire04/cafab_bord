<?php

use App\Enums\UserRole;
use App\Models\Coach;
use App\Models\Fille;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Facades\Excel;

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => UserRole::Admin]);
});

function buildImportSpreadsheet(array $rows): UploadedFile
{
    // Laravel 12's default 'local' disk root is storage/app/private (not storage/app),
    // so Excel::store(..., 'local') lands the file there.
    $path = storage_path('app/private/test-import.xlsx');

    Excel::store(
        new class($rows) implements FromArray
        {
            public function __construct(private array $rows) {}

            public function array(): array
            {
                return array_merge([['nom', 'prenom', 'contact']], $this->rows);
            }
        },
        'test-import.xlsx',
        'local'
    );

    return new UploadedFile($path, 'import.xlsx', null, null, true);
}

it('lets a coach open the Excel import', function () {
    $coach = Coach::factory()->create();

    $this->actingAs($coach->user)->get(route('filles.import'))->assertOk();
});

it('previews rows and flags a duplicate by nom+prenom', function () {
    Fille::factory()->create(['nom' => 'Dossou', 'prenom' => 'Grâce']);

    $file = buildImportSpreadsheet([
        ['Hounkpatin', 'Sènami', '+229 01 00 00 00 00'],
        ['Dossou', 'Grâce', ''],
    ]);

    $response = $this->actingAs($this->admin)
        ->post(route('filles.import.preview'), ['fichier' => $file]);

    $response->assertOk();
    $response->assertSee('Hounkpatin');
    $response->assertSee('doublon potentiel', false);

    expect(Fille::count())->toBe(1);
});

it('confirms the import and only creates the rows kept by the admin', function () {
    $file = buildImportSpreadsheet([
        ['Kpossou', 'Yasmine', ''],
        ['Ahouandjinou', 'Lucrèce', ''],
    ]);

    $this->actingAs($this->admin)->post(route('filles.import.preview'), ['fichier' => $file]);

    $response = $this->actingAs($this->admin)->post(route('filles.import.confirm'), [
        'lignes' => [0, 1],
    ]);

    $response->assertRedirect(route('filles.index'));

    expect(Fille::count())->toBe(2);
    expect(Fille::where('nom', 'Kpossou')->exists())->toBeTrue();
    $created = Fille::where('nom', 'Kpossou')->first();
    expect($created->pin)->toMatch('/^\d{4}$/');
});
