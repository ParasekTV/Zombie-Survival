# ==========================================
# FTP UPLOAD SKRIPT FÜR ZOMBIE SURVIVAL
# ==========================================
# Anleitung:
# 1. Tragen Sie unten Ihre FTP-Daten ein.
# 2. Speichern Sie die Datei.
# 3. Rechtsklick auf diese Datei -> "Mit PowerShell ausführen".
# ==========================================

$ftpHost = ""   # z.B. ftp.example.com
$ftpUser = ""     # Ihr FTP Username
$ftpPass = ""         # Ihr FTP Passwort
$ftpDir  = "/"               # Zielordner auf dem Server (muss existieren!)

# Lokaler Ordner (das aktuelle Verzeichnis des Skripts)
$localDir = $PSScriptRoot

# Dateien die NICHT hochgeladen werden sollen
$exclude = @("deploy.ps1", ".git", ".vscode", "README.md", "task.md", "implementation_plan.md")

# ==========================================
# LOGIK (NICHT ÄNDERN)
# ==========================================

$webclient = New-Object System.Net.WebClient
$webclient.Credentials = New-Object System.Net.NetworkCredential($ftpUser, $ftpPass)

# Funktion zum rekursiven Hochladen
function Upload-Directory($path, $remotePath) {
    $files = Get-ChildItem $path

    foreach ($file in $files) {
        if ($exclude -contains $file.Name) { continue }

        $remoteFilePath = $remotePath + "/" + $file.Name
        # Bereinige Doppelte Slashes
        $remoteFilePath = $remoteFilePath -replace "//", "/"

        if ($file.Attributes -band [System.IO.FileAttributes]::Directory) {
            # Verzeichnis erstellen (ignoriere Fehler wenn es schon existiert)
            try {
                $uri = New-Object System.Uri("ftp://$($ftpHost)$($remoteFilePath)")
                $request = [System.Net.WebRequest]::Create($uri)
                $request.Method = [System.Net.WebRequestMethods+Ftp]::MakeDirectory
                $request.Credentials = $webclient.Credentials
                $null = $request.GetResponse()
                Write-Host "Verzeichnis erstellt: $remoteFilePath" -ForegroundColor Cyan
            } catch {
                # Ignorieren, meistens "Directory already exists"
            }
            # Rekursion
            Upload-Directory $file.FullName $remoteFilePath
        } else {
            # Datei hochladen
            Write-Host "Lade hoch: $($file.Name)..." -NoNewline
            $uri = New-Object System.Uri("ftp://$($ftpHost)$($remoteFilePath)")
            try {
                $webclient.UploadFile($uri, $file.FullName)
                Write-Host " OK" -ForegroundColor Green
            } catch {
                Write-Host " FEHLER: $($_.Exception.Message)" -ForegroundColor Red
            }
        }
    }
}

Write-Host "Starte Upload nach ftp://$ftpHost$ftpDir..." -ForegroundColor Yellow
Upload-Directory $localDir $ftpDir
Write-Host "Fertig!" -ForegroundColor Yellow
Pause
