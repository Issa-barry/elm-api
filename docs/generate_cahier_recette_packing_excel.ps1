$ErrorActionPreference = 'Stop'

$sourceScript = Join-Path $PSScriptRoot 'generate_cahier_recette_packing.ps1'
if (-not (Test-Path $sourceScript)) {
    throw "Script source introuvable: $sourceScript"
}

$cases = New-Object System.Collections.Generic.List[object]
$addCasePattern = "^\s*Add-Case\s"
$argPattern = "'((?:''|[^'])*)'"

foreach ($line in Get-Content $sourceScript) {
    if ($line -notmatch $addCasePattern) {
        continue
    }

    $matches = [regex]::Matches($line, $argPattern)
    if ($matches.Count -lt 8) {
        continue
    }

    $values = @()
    foreach ($m in $matches) {
        $values += $m.Groups[1].Value.Replace("''", "'")
    }

    $cases.Add([pscustomobject]@{
            ID              = ('PACK-{0:d3}' -f ($cases.Count + 1))
            Module          = $values[0]
            SousModule      = $values[1]
            Priorite        = $values[2]
            Type            = $values[3]
            Preconditions   = $values[4]
            Etapes          = $values[5]
            Donnees         = $values[6]
            ResultatAttendu = $values[7]
        })
}

if ($cases.Count -eq 0) {
    throw 'Aucun cas detecte dans le script source.'
}

$outputPath = Join-Path $PSScriptRoot 'cahier_recette_packing_front.xlsx'

$headers = @(
    'ID',
    'Module',
    'Sous-module',
    'Priorite',
    'Type',
    'Preconditions',
    'Etapes',
    'Donnees de test',
    'Resultat attendu',
    'Resultat obtenu',
    'Statut (OK/KO/NT)',
    'Severite anomalie',
    'Capture / preuve',
    'Testeur',
    'Date execution',
    'Commentaires'
)

$excel = $null
$workbook = $null
$sheetCases = $null
$sheetSynth = $null

try {
    $excel = New-Object -ComObject Excel.Application
    $excel.Visible = $false
    $excel.DisplayAlerts = $false

    $workbook = $excel.Workbooks.Add()
    $sheetCases = $workbook.Worksheets.Item(1)
    $sheetCases.Name = 'Cas_de_test'

    $sheetSynth = $workbook.Worksheets.Add()
    $sheetSynth.Name = 'Synthese'

    while ($workbook.Worksheets.Count -gt 2) {
        $workbook.Worksheets.Item($workbook.Worksheets.Count).Delete()
    }

    $lastRow = $cases.Count + 1
    $matrix = New-Object 'object[,]' $lastRow, 16

    for ($col = 0; $col -lt 16; $col++) {
        $matrix[0, $col] = $headers[$col]
    }

    for ($i = 0; $i -lt $cases.Count; $i++) {
        $case = $cases[$i]
        $row = $i + 1

        $matrix[$row, 0] = $case.ID
        $matrix[$row, 1] = $case.Module
        $matrix[$row, 2] = $case.SousModule
        $matrix[$row, 3] = $case.Priorite
        $matrix[$row, 4] = $case.Type
        $matrix[$row, 5] = $case.Preconditions
        $matrix[$row, 6] = $case.Etapes
        $matrix[$row, 7] = $case.Donnees
        $matrix[$row, 8] = $case.ResultatAttendu
        $matrix[$row, 10] = 'NT'
    }

    $sheetCases.Range("A1", "P$lastRow").Value2 = $matrix
    $sheetCases.Range('A1:P1').Font.Bold = $true
    $sheetCases.Range("A1:P$lastRow").AutoFilter() | Out-Null
    $sheetCases.Columns.Item('A').ColumnWidth = 12
    $sheetCases.Columns.Item('B').ColumnWidth = 24
    $sheetCases.Columns.Item('C').ColumnWidth = 26
    $sheetCases.Columns.Item('D').ColumnWidth = 10
    $sheetCases.Columns.Item('E').ColumnWidth = 16
    $sheetCases.Columns.Item('F').ColumnWidth = 40
    $sheetCases.Columns.Item('G').ColumnWidth = 38
    $sheetCases.Columns.Item('H').ColumnWidth = 24
    $sheetCases.Columns.Item('I').ColumnWidth = 42
    $sheetCases.Columns.Item('J').ColumnWidth = 28
    $sheetCases.Columns.Item('K').ColumnWidth = 15
    $sheetCases.Columns.Item('L').ColumnWidth = 18
    $sheetCases.Columns.Item('M').ColumnWidth = 18
    $sheetCases.Columns.Item('N').ColumnWidth = 16
    $sheetCases.Columns.Item('O').ColumnWidth = 14
    $sheetCases.Columns.Item('P').ColumnWidth = 30
    $sheetCases.Range("F2:I$lastRow").WrapText = $true
    $sheetCases.Range("P2:P$lastRow").WrapText = $true
    $sheetCases.Range("K2:K$lastRow").Validation.Delete()
    $sheetCases.Range("K2:K$lastRow").Validation.Add(3, 1, 1, 'OK,KO,NT')

    $sheetSynth.Range('A1').Value2 = 'Indicateur'
    $sheetSynth.Range('B1').Value2 = 'Valeur'
    $sheetSynth.Range('D1').Value2 = 'Module'
    $sheetSynth.Range('E1').Value2 = 'Total'
    $sheetSynth.Range('F1').Value2 = 'OK'
    $sheetSynth.Range('G1').Value2 = 'KO'
    $sheetSynth.Range('H1').Value2 = '% OK'
    $sheetSynth.Range('I1').Value2 = '% KO'
    $sheetSynth.Range('A1:B1').Font.Bold = $true
    $sheetSynth.Range('D1:I1').Font.Bold = $true

    $sheetSynth.Range('A2').Value2 = 'Total cas'
    $sheetSynth.Range('B2').Formula = '=COUNTA(Cas_de_test!$A$2:$A$9999)'
    $sheetSynth.Range('A3').Value2 = 'Cas OK'
    $sheetSynth.Range('B3').Formula = '=COUNTIF(Cas_de_test!$K$2:$K$9999,"OK")'
    $sheetSynth.Range('A4').Value2 = 'Cas KO'
    $sheetSynth.Range('B4').Formula = '=COUNTIF(Cas_de_test!$K$2:$K$9999,"KO")'
    $sheetSynth.Range('A5').Value2 = 'Cas NT'
    $sheetSynth.Range('B5').Formula = '=COUNTIF(Cas_de_test!$K$2:$K$9999,"NT")'
    $sheetSynth.Range('A6').Value2 = 'Cas executes (OK+KO)'
    $sheetSynth.Range('B6').Formula = '=B3+B4'
    $sheetSynth.Range('A7').Value2 = '% Execution'
    $sheetSynth.Range('B7').Formula = '=IF(B2=0,0,B6/B2)'
    $sheetSynth.Range('A8').Value2 = '% OK (sur executes)'
    $sheetSynth.Range('B8').Formula = '=IF(B6=0,0,B3/B6)'
    $sheetSynth.Range('A9').Value2 = '% KO (sur executes)'
    $sheetSynth.Range('B9').Formula = '=IF(B6=0,0,B4/B6)'
    $sheetSynth.Range('B7:B9').NumberFormat = '0.00%'

    $modules = $cases | Select-Object -ExpandProperty Module -Unique
    $rowModule = 2
    foreach ($module in $modules) {
        $sheetSynth.Range("D$rowModule").Value2 = $module
        $sheetSynth.Range("E$rowModule").Formula = "=COUNTIF(Cas_de_test!`$B`$2:`$B`$9999,D$rowModule)"
        $sheetSynth.Range("F$rowModule").Formula = "=COUNTIFS(Cas_de_test!`$B`$2:`$B`$9999,D$rowModule,Cas_de_test!`$K`$2:`$K`$9999,""OK"")"
        $sheetSynth.Range("G$rowModule").Formula = "=COUNTIFS(Cas_de_test!`$B`$2:`$B`$9999,D$rowModule,Cas_de_test!`$K`$2:`$K`$9999,""KO"")"
        $sheetSynth.Range("H$rowModule").Formula = "=IF((F$rowModule+G$rowModule)=0,0,F$rowModule/(F$rowModule+G$rowModule))"
        $sheetSynth.Range("I$rowModule").Formula = "=IF((F$rowModule+G$rowModule)=0,0,G$rowModule/(F$rowModule+G$rowModule))"
        $rowModule++
    }
    if ($rowModule -gt 2) {
        $sheetSynth.Range("H2:I$($rowModule - 1)").NumberFormat = '0.00%'
    }

    $sheetSynth.Range('A12').Value2 = 'Mode emploi'
    $sheetSynth.Range('A13').Value2 = '1. Executer les tests de Cas_de_test.'
    $sheetSynth.Range('A14').Value2 = '2. Mettre Statut sur OK, KO ou NT.'
    $sheetSynth.Range('A15').Value2 = '3. Renseigner Resultat obtenu + preuve en cas KO.'
    $sheetSynth.Range('A12').Font.Bold = $true

    $sheetSynth.Columns.Item('A').ColumnWidth = 34
    $sheetSynth.Columns.Item('B').ColumnWidth = 18
    $sheetSynth.Columns.Item('D').ColumnWidth = 26
    $sheetSynth.Columns.Item('E').ColumnWidth = 10
    $sheetSynth.Columns.Item('F').ColumnWidth = 10
    $sheetSynth.Columns.Item('G').ColumnWidth = 10
    $sheetSynth.Columns.Item('H').ColumnWidth = 12
    $sheetSynth.Columns.Item('I').ColumnWidth = 12

    if (Test-Path $outputPath) {
        Remove-Item $outputPath -Force
    }

    $workbook.SaveAs($outputPath, 51)
}
finally {
    if ($workbook) {
        try { $workbook.Close($true) | Out-Null } catch {}
    }
    if ($excel) {
        try { $excel.Quit() | Out-Null } catch {}
    }

    if ($sheetSynth) {
        [void][System.Runtime.InteropServices.Marshal]::ReleaseComObject($sheetSynth)
    }
    if ($sheetCases) {
        [void][System.Runtime.InteropServices.Marshal]::ReleaseComObject($sheetCases)
    }
    if ($workbook) {
        [void][System.Runtime.InteropServices.Marshal]::ReleaseComObject($workbook)
    }
    if ($excel) {
        [void][System.Runtime.InteropServices.Marshal]::ReleaseComObject($excel)
    }
}

Write-Output "Cahier de recette genere: $outputPath"
Write-Output "Nombre de cas: $($cases.Count)"
