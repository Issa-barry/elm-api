$dirs = @(
    'C:\laragon\www\elm-api\app\Http\Controllers\Produit',
    'C:\laragon\www\elm-api\app\Http\Controllers\Packing',
    'C:\laragon\www\elm-api\app\Http\Controllers\Ventes',
    'C:\laragon\www\elm-api\app\Http\Requests\Produit',
    'C:\laragon\www\elm-api\app\Http\Requests\Vehicule',
    'C:\laragon\www\elm-api\app\Http\Requests\Prestataire',
    'C:\laragon\www\elm-api\app\Models'
)

$replacements = @(
    # withoutUsineScope → withoutSiteScope
    @('withoutUsineScope()', 'withoutSiteScope()'),
    # Closure type hints
    @('function (Usine $usine)', 'function (Site $site)'),
    @('function(Usine $usine)', 'function(Site $site)'),
    # $usine->id inside closures (after renaming param to $site)
    @('$usine->id', '$site->id'),
    @('$usine->nom', '$site->nom'),
    @('$usine->code', '$site->code'),
    # load relation 'usine:' → 'site:'
    @("load('usine:", "load('site:"),
    @('load("usine:', 'load("site:'),
    # Request field names
    @("'usines.*.usine_id'", "'usines.*.site_id'"),
    # Comments only (leave the PHP code change above covers it)
    @("'usine_id.required'", "'site_id.required'"),
    @("'usine_id.exists'", "'site_id.exists'"),
    # StoreVehiculeRequest validation message keys
    @("'usine_id.required'", "'site_id.required'")
)

$phpFiles = Get-ChildItem -Path $dirs -Recurse -Filter '*.php' -ErrorAction SilentlyContinue

$count = 0
foreach ($file in $phpFiles) {
    $content = [System.IO.File]::ReadAllText($file.FullName)
    $original = $content

    foreach ($pair in $replacements) {
        $content = $content.Replace($pair[0], $pair[1])
    }

    if ($content -ne $original) {
        [System.IO.File]::WriteAllText($file.FullName, $content)
        Write-Host "Updated: $($file.Name)"
        $count++
    }
}
Write-Host "Done. $count files updated."
