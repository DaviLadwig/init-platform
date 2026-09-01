$ErrorActionPreference = "Stop"

$taskName = "Init SaaS - Webhooks Asaas"
$projectPath = "C:\xampp\htdocs\init-platform"
$cmdPath = Join-Path $projectPath "scripts\windows\processar-webhooks-asaas.cmd"

if (-not (Test-Path $cmdPath)) {
    throw "Arquivo não encontrado: $cmdPath"
}

# A versão anterior usava [TimeSpan]::MaxValue em RepetitionDuration.
# O Agendador de Tarefas do Windows não aceita essa duração no XML.
#
# Para executar continuamente a cada 1 minuto, usamos o utilitário
# nativo schtasks.exe com /SC MINUTE /MO 1.

$taskCommand = "cmd.exe /c `"$cmdPath`""

& schtasks.exe `
    /Create `
    /TN $taskName `
    /TR $taskCommand `
    /SC MINUTE `
    /MO 1 `
    /F | Out-Host

if ($LASTEXITCODE -ne 0) {
    throw "Não foi possível criar a tarefa agendada '$taskName'. Código: $LASTEXITCODE"
}

Write-Host ""
Write-Host "Tarefa criada com sucesso: $taskName"
Write-Host "Frequência: a cada 1 minuto"
Write-Host ""
Write-Host "Valide com:"
Write-Host "Get-ScheduledTask -TaskName `"$taskName`""
