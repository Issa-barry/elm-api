$dirs = @(
    'C:\laragon\www\elm-api\app',
    'C:\laragon\www\elm-api\database\seeders',
    'C:\laragon\www\elm-api\tests'
)

$replacements = @(
    # Import statements
    @('use App\Services\UsineContext;', 'use App\Services\SiteContext;'),
    @('use App\Models\Usine;', 'use App\Models\Site;'),
    @('use App\Models\UserUsine;', 'use App\Models\UserSite;'),
    @('use App\Models\ProduitUsine;', 'use App\Models\ProduitSite;'),
    @('use App\Enums\UsineRole;', 'use App\Enums\SiteRole;'),
    @('use App\Enums\UsineType;', 'use App\Enums\SiteType;'),
    @('use App\Enums\UsineStatut;', 'use App\Enums\SiteStatut;'),
    # Class method calls
    @('UserUsine::', 'UserSite::'),
    @('ProduitUsine::', 'ProduitSite::'),
    @('Usine::', 'Site::'),
    @('UsineRole::', 'SiteRole::'),
    @('UsineType::', 'SiteType::'),
    @('UsineStatut::', 'SiteStatut::'),
    # Context class
    @('UsineContext::class', 'SiteContext::class'),
    @('UsineContext', 'SiteContext'),
    # Methods
    @('->getCurrentUsineId()', '->getCurrentSiteId()'),
    @('->setCurrentUsineId(', '->setCurrentSiteId('),
    @('->hasUsineAccess(', '->hasSiteAccess('),
    @('hasUsineAccess', 'hasSiteAccess'),
    # DB column string literals
    @("'usine_id'", "'site_id'"),
    # Property access
    @('->usine_id', '->site_id'),
    # Validation table references
    @(':usines,', ':sites,'),
    @("Rule::unique('usines'", "Rule::unique('sites'"),
    @("Rule::exists('usines'", "Rule::exists('sites'"),
    # Table name strings
    @("'produit_usines'", "'produit_sites'"),
    @("'user_usines'", "'user_sites'"),
    @("table('usines')", "table('sites')"),
    # Trait
    @('HasUsineScope', 'HasSiteScope'),
    # Headers
    @('X-Usine-Id', 'X-Site-Id'),
    # Variable names
    @('$usineContext', '$siteContext'),
    @('$usineId', '$siteId'),
    @('$usines', '$sites'),
    # Constructor
    @('new Usine(', 'new Site(')
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
