<?php

namespace Tests\Feature;

use App\Enums\PieceType;
use App\Http\Requests\User\StoreUserRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class KycValidationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::findOrCreate('admin', 'web');
    }
    /**
     * Données de base valides (hors KYC) pour StoreUserRequest.
     */
    private function basePayload(array $overrides = []): array
    {
        return array_merge([
            'nom'             => 'Diallo',
            'prenom'          => 'Mamadou',
            'phone'           => '+224620000001',
            'type'            => 'staff',
            'role'            => 'admin',
            'pays'            => 'Guinée',
            'code_pays'       => 'GN',
            'code_phone_pays' => '+224',
            'ville'           => 'Conakry',
            'quartier'        => 'Kaloum',
            'password'        => 'secret1234',
        ], $overrides);
    }

    /**
     * Instancie un StoreUserRequest avec les données fournies
     * et lance la validation manuellement (sans toucher la BDD).
     */
    private function validateStore(array $data): \Illuminate\Validation\Validator
    {
        $request = StoreUserRequest::create('/api/users', 'POST', $data);
        $request->setContainer(app());

        // Appeler prepareForValidation via reflection
        $method = new \ReflectionMethod($request, 'prepareForValidation');
        $method->setAccessible(true);
        $method->invoke($request);

        return Validator::make(
            $request->all(),
            $request->rules(),
            $request->messages()
        );
    }

    // ─────────────────────────────────────────────────────
    //  Cas 1 : piece_type null → tout null → OK
    // ─────────────────────────────────────────────────────

    public function test_kyc_all_null_when_piece_type_absent(): void
    {
        $validator = $this->validateStore($this->basePayload([
            'piece_type'        => null,
            'piece_numero'      => null,
            'piece_pays'        => null,
            'piece_delivree_le' => null,
            'piece_expire_le'   => null,
        ]));

        $this->assertFalse(
            $validator->fails(),
            'KYC tout null devrait passer. Erreurs : ' . json_encode($validator->errors()->toArray())
        );
    }

    // ─────────────────────────────────────────────────────
    //  Cas 2 : piece_type = passeport mais piece_numero manquant → KO
    // ─────────────────────────────────────────────────────

    public function test_kyc_requires_numero_when_type_present(): void
    {
        $validator = $this->validateStore($this->basePayload([
            'piece_type'        => 'passeport',
            'piece_numero'      => null,
            'piece_pays'        => 'GN',
            'piece_delivree_le' => '2023-01-15',
            'piece_expire_le'   => '2033-01-15',
        ]));

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('piece_numero', $validator->errors()->toArray());
    }

    // ─────────────────────────────────────────────────────
    //  Cas 3 : expire_le < delivree_le → KO
    // ─────────────────────────────────────────────────────

    public function test_kyc_expire_must_be_after_delivree(): void
    {
        $validator = $this->validateStore($this->basePayload([
            'piece_type'        => 'cni',
            'piece_numero'      => 'CNI-123456',
            'piece_pays'        => 'GN',
            'piece_delivree_le' => '2024-06-01',
            'piece_expire_le'   => '2024-01-01', // avant delivree
        ]));

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('piece_expire_le', $validator->errors()->toArray());
    }

    // ─────────────────────────────────────────────────────
    //  Cas 4 : piece_numero envoyé sans piece_type → KO (exige piece_type)
    // ─────────────────────────────────────────────────────

    public function test_kyc_requires_type_when_other_kyc_field_present(): void
    {
        $validator = $this->validateStore($this->basePayload([
            'piece_type'   => null,
            'piece_numero' => 'ABC-999',
        ]));

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('piece_type', $validator->errors()->toArray());
    }

    // ─────────────────────────────────────────────────────
    //  Cas 5 : bloc KYC complet et valide → OK
    // ─────────────────────────────────────────────────────

    public function test_kyc_complete_valid_block_passes(): void
    {
        $validator = $this->validateStore($this->basePayload([
            'piece_type'        => 'passeport',
            'piece_numero'      => 'P-123456789',
            'piece_pays'        => 'GN',
            'piece_delivree_le' => '2023-06-01',
            'piece_expire_le'   => '2033-06-01',
        ]));

        $this->assertFalse(
            $validator->fails(),
            'Bloc KYC complet valide devrait passer. Erreurs : ' . json_encode($validator->errors()->toArray())
        );
    }

    // ─────────────────────────────────────────────────────
    //  Cas 6 : piece_type normalisé en lowercase
    // ─────────────────────────────────────────────────────

    public function test_kyc_piece_type_normalized_to_lowercase(): void
    {
        $request = StoreUserRequest::create('/api/users', 'POST', $this->basePayload([
            'piece_type' => 'PASSEPORT',
        ]));
        $request->setContainer(app());

        $method = new \ReflectionMethod($request, 'prepareForValidation');
        $method->setAccessible(true);
        $method->invoke($request);

        $this->assertEquals('passeport', $request->input('piece_type'));
    }

    // ─────────────────────────────────────────────────────
    //  Cas 7 : strict expiry — pièce expirée → KO
    // ─────────────────────────────────────────────────────

    public function test_kyc_strict_expiry_rejects_expired_piece(): void
    {
        config(['kyc.strict_expiry' => true]);

        $validator = $this->validateStore($this->basePayload([
            'piece_type'        => 'cni',
            'piece_numero'      => 'CNI-789',
            'piece_pays'        => 'GN',
            'piece_delivree_le' => '2020-01-01',
            'piece_expire_le'   => '2022-01-01', // expiré
        ]));

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('piece_expire_le', $validator->errors()->toArray());
    }

    // ─────────────────────────────────────────────────────
    //  Cas 8 : souple — pièce expirée → OK
    // ─────────────────────────────────────────────────────

    public function test_kyc_souple_expiry_allows_expired_piece(): void
    {
        config(['kyc.strict_expiry' => false]);

        $validator = $this->validateStore($this->basePayload([
            'piece_type'        => 'cni',
            'piece_numero'      => 'CNI-789',
            'piece_pays'        => 'GN',
            'piece_delivree_le' => '2020-01-01',
            'piece_expire_le'   => '2022-01-01', // expiré mais souple
        ]));

        $this->assertFalse(
            $validator->fails(),
            'En mode souple, une pièce expirée devrait passer. Erreurs : ' . json_encode($validator->errors()->toArray())
        );
    }
}
