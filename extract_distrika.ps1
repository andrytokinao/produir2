# Script pour extraire les districts du fichier Excel
$excelFile = "c:\xampp\htdocs\Produir2\FKT.xlsx"

try {
    $excel = New-Object -ComObject Excel.Application
    $excel.Visible = $false
    $excel.DisplayAlerts = $false
    
    $workbook = $excel.Workbooks.Open($excelFile)
    $worksheet = $workbook.Sheets.Item(1)
    
    # Trouver la colonne DISTRIKA
    $distrikaCol = $null
    for ($col = 1; $col -le $worksheet.UsedRange.Columns.Count; $col++) {
        $headerValue = $worksheet.Cells.Item(1, $col).Text
        if ($headerValue -eq 'DISTRIKA') {
            $distrikaCol = $col
            break
        }
    }
    
    if ($distrikaCol) {
        # Extraire les valeurs uniques
        $distrikas = @{}
        for ($row = 2; $row -le $worksheet.UsedRange.Rows.Count; $row++) {
            $value = $worksheet.Cells.Item($row, $distrikaCol).Text
            if ($value -and $value.Trim() -ne '') {
                $distrikas[$value.Trim()] = $true
            }
        }
        
        # Trier et afficher
        $sorted = $distrikas.Keys | Sort-Object
        $sorted | ConvertTo-Json
    } else {
        Write-Host "Colonne DISTRIKA non trouvée"
    }
    
    $workbook.Close($false)
    $excel.Quit()
    [System.Runtime.Interopservices.Marshal]::ReleaseComObject($worksheet) | Out-Null
    [System.Runtime.Interopservices.Marshal]::ReleaseComObject($workbook) | Out-Null
    [System.Runtime.Interopservices.Marshal]::ReleaseComObject($excel) | Out-Null
    [System.GC]::Collect()
    [System.GC]::WaitForPendingFinalizers()
}
catch {
    Write-Host "Erreur: $_"
}
