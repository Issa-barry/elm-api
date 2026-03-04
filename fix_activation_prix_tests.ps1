
# Fix ProduitUsineActivationTest - creerProduitGlobal helper needs code
$file1 = 'C:\laragon\www\elm-api\tests\Feature\Produit\ProduitUsineActivationTest.php'
$c = [System.IO.File]::ReadAllText($file1)

# Add code to the creerProduitGlobal helper
$c = $c.Replace(
    "return Produit::withoutGlobalScopes()->create([" + [System.Environment]::NewLine + "            'nom'       => `$nom,",
    "return Produit::withoutGlobalScopes()->create([" + [System.Environment]::NewLine + "            'nom'       => `$nom," + [System.Environment]::NewLine + "            'code'      => substr(md5(uniqid('ACT-')), 0, 12),"
)

# Add code to the 'Service POS' create
$c = $c.Replace(
    "'nom'       => 'Service POS',",
    "'nom'       => 'Service POS'," + [System.Environment]::NewLine + "            'code'      => 'ACT-SVC-001',"
)

[System.IO.File]::WriteAllText($file1, $c)
Write-Host "Fixed ProduitUsineActivationTest.php"

# Fix ProduitUsinePrixTest - creerProduitGlobal helper needs code
$file2 = 'C:\laragon\www\elm-api\tests\Feature\Produit\ProduitUsinePrixTest.php'
$c = [System.IO.File]::ReadAllText($file2)

# The creerProduitGlobal helper uses uniqid() for nom, add code similarly
$c = $c.Replace(
    "'nom'        => 'Produit prix test ' . uniqid(),",
    "'nom'        => 'Produit prix test ' . uniqid()," + [System.Environment]::NewLine + "            'code'       => substr(md5(uniqid('PRIX-')), 0, 12),"
)

[System.IO.File]::WriteAllText($file2, $c)
Write-Host "Fixed ProduitUsinePrixTest.php"
