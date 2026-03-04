$files = @(
    # Controllers with isAllUsines
    'C:\laragon\www\elm-api\app\Http\Controllers\Produit\ProduitIndexController.php',
    'C:\laragon\www\elm-api\app\Http\Controllers\Produit\ProduitShowController.php',
    # Tests with ->usines()->attach() and other usines() calls
    'C:\laragon\www\elm-api\tests\Feature\Livraison\FactureEncaissementTest.php',
    'C:\laragon\www\elm-api\tests\Feature\Livraison\VehiculeTest.php',
    'C:\laragon\www\elm-api\tests\Feature\Organisation\OrganisationAuthorizationTest.php',
    'C:\laragon\www\elm-api\tests\Feature\Vente\CommandeVenteTest.php',
    'C:\laragon\www\elm-api\tests\Feature\Vente\FactureVenteTest.php'
)

$replacements = @(
    @('isAllUsines()', 'isAllSites()'),
    @('$allUsines', '$allSites'),
    @('->usines()->attach(', '->sites()->attach('),
    @('->usines()->detach(', '->sites()->detach('),
    @('->usines()->sync(', '->sites()->sync('),
    @('->usines()->updateExistingPivot(', '->sites()->updateExistingPivot(')
)

$count = 0
foreach ($file in $files) {
    if (-not (Test-Path $file)) { continue }
    $content = [System.IO.File]::ReadAllText($file)
    $original = $content

    foreach ($pair in $replacements) {
        $content = $content.Replace($pair[0], $pair[1])
    }

    if ($content -ne $original) {
        [System.IO.File]::WriteAllText($file, $content)
        Write-Host "Updated: $(Split-Path $file -Leaf)"
        $count++
    }
}
Write-Host "Done. $count files updated."
