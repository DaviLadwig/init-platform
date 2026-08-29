$ErrorActionPreference = 'Stop'

$taskName = 'Init SaaS - Inadimplencia'
$projectRoot = 'C:\xampp\htdocs\init-platform'
$runner = Join-Path $projectRoot 'scripts\windows\processar-inadimplencia.cmd'

if (-not (Test-Path $runner)) {
    throw "Runner não encontrado: $runner"
}

$action = New-ScheduledTaskAction `
    -Execute 'cmd.exe' `
    -Argument ('/c "' + $runner + '"') `
    -WorkingDirectory $projectRoot

$trigger = New-ScheduledTaskTrigger `
    -Daily `
    -At '00:10'

$settings = New-ScheduledTaskSettingsSet `
    -StartWhenAvailable `
    -MultipleInstances IgnoreNew `
    -ExecutionTimeLimit (New-TimeSpan -Minutes 10)

Register-ScheduledTask `
    -TaskName $taskName `
    -Action $action `
    -Trigger $trigger `
    -Settings $settings `
    -Description 'Atualiza automaticamente assinaturas atrasadas e suspensas da Init SaaS Platform.' `
    -Force | Out-Null

Write-Host "Tarefa criada: $taskName"
Write-Host "Execução diária: 00:10"
