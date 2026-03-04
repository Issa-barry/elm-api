$file = 'C:\laragon\www\elm-api\tests\Feature\Produit\ProduitGlobalTest.php'
$c = [System.IO.File]::ReadAllText($file)

# Fix scope key 'usine' -> 'site'
$c = $c.Replace("withoutGlobalScope('usine')", "withoutGlobalScope('site')")

# Add code after each nom line in Produit creates
$pairs = @(
    @("'nom'       => 'Produit global test',", "'nom'       => 'Produit global test'," + "`n" + "            'code'      => 'GLBT-001',"),
    @("'nom'       => 'Produit local test',", "'nom'       => 'Produit local test'," + "`n" + "            'code'      => 'GLBT-002',"),
    @("'nom'       => 'Global visible partout',", "'nom'       => 'Global visible partout'," + "`n" + "            'code'      => 'GLBT-003',"),
    @("'nom'       => 'Global toutes usines',", "'nom'       => 'Global toutes usines'," + "`n" + "            'code'      => 'GLBT-004',"),
    @("'nom'       => 'Local usine A seulement',", "'nom'       => 'Local usine A seulement'," + "`n" + "            'code'      => 'GLBT-005',"),
    @("'nom'       => 'Produit global pre-usine',", "'nom'       => 'Produit global pre-usine'," + "`n" + "            'code'      => 'GLBT-006',"),
    @("'nom'       => 'Produit global stockable',", "'nom'       => 'Produit global stockable'," + "`n" + "            'code'      => 'GLBT-007',"),
    @("'nom'       => 'Sans config usine',", "'nom'       => 'Sans config usine'," + "`n" + "            'code'      => 'GLBT-008',"),
    @("'nom'       => 'Avec config usine A',", "'nom'       => 'Avec config usine A'," + "`n" + "            'code'      => 'GLBT-009',"),
    @("'nom'       => 'Actif local',", "'nom'       => 'Actif local'," + "`n" + "            'code'      => 'GLBT-010',"),
    @("'nom'       => 'Inactif local',", "'nom'       => 'Inactif local'," + "`n" + "            'code'      => 'GLBT-011',"),
    @("'nom'       => 'Global liste',", "'nom'       => 'Global liste'," + "`n" + "            'code'      => 'GLBT-012',")
)

foreach ($pair in $pairs) {
    $c = $c.Replace($pair[0], $pair[1])
}

[System.IO.File]::WriteAllText($file, $c)
Write-Host "Done. Updated ProduitGlobalTest.php"
