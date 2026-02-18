# Test script to inspect FKT.xlsx structure
$excel = New-Object -ComObject Excel.Application
$excel.Visible = $false
$workbook = $excel.Workbooks.Open("C:\xampp\htdocs\Produir2\FKT.xlsx")
$worksheet = $workbook.Worksheets.Item(1)

# Get headers
Write-Host "=== HEADERS ===" -ForegroundColor Cyan
$headers = @()
$col = 1
while ($worksheet.Cells.Item(1, $col).Text -ne "") {
    $headerName = $worksheet.Cells.Item(1, $col).Text
    $headers += $headerName
    Write-Host "Column $col : $headerName" -ForegroundColor Yellow
    $col++
}

# Show first 5 rows
Write-Host "`n=== FIRST 5 ROWS ===" -ForegroundColor Cyan
for ($row = 2; $row -le 6; $row++) {
    Write-Host "`nRow $row:" -ForegroundColor Green
    for ($col = 1; $col -le $headers.Count; $col++) {
        $value = $worksheet.Cells.Item($row, $col).Text
        Write-Host "  $($headers[$col-1]): $value"
    }
}

# Count unique districts
Write-Host "`n=== DISTRICTS ===" -ForegroundColor Cyan
$districts = @{}
$row = 2
while ($worksheet.Cells.Item($row, 1).Text -ne "") {
    $distrika = $worksheet.Cells.Item($row, 1).Text
    if ($distrika -ne "") {
        $districts[$distrika] = $true
    }
    $row++
}
Write-Host "Unique districts: $($districts.Keys.Count)" -ForegroundColor Yellow
$districts.Keys | ForEach-Object { Write-Host "  - $_" }

$workbook.Close($false)
$excel.Quit()
[System.Runtime.Interopservices.Marshal]::ReleaseComObject($excel) | Out-Null
Write-Host "`n✅ Done" -ForegroundColor Green
