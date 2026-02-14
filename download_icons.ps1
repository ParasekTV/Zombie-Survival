# Download Icons Script
Param (
    [string]$BaseUrl = "https://raw.githubusercontent.com/game-icons/icons/master"
)

# Function to download
function Get-Icon {
    param (
        [string]$Author,
        [string]$Name,
        [string]$TargetCategory,
        [string]$TargetName
    )
    
    $Source = "$BaseUrl/$Author/$Name.svg"
    # We need to convert SVG to PNG or just use SVG if browser supports (it does!)
    # Actually, for simplicity in PHP, let's keep it consistent.
    # PHP script expects PNG in config.php. We should change config.php to support SVG or download PNGs.
    # Game-Icons.net repo only updated SVGs.
    # We will download SVGs and save them as .png file extension (browser will render SVG in IMG tag even if ext is png often, BUT better to rename properly).
    # BETTER: We update config.php to look for .svg first, then .png!
    
    $SourceDir = "d:\repos\zsbg\assets\icons\$TargetCategory"
    if (!(Test-Path -Path $SourceDir)) {
        New-Item -ItemType Directory -Force -Path $SourceDir | Out-Null
    }
    
    $Dest = "$SourceDir\$TargetName.svg"
    echo "Downloading $Name to $Dest..."
    try {
        Invoke-WebRequest -Uri $Source -OutFile $Dest
    }
    catch {
        echo "Error downloading $Name"
    }
}

# Stats
Get-Icon "zeromancer" "heart-plus" "stats" "health"
Get-Icon "lorc" "chicken-leg" "stats" "satiety"
Get-Icon "lorc" "lightning-trio" "stats" "energy"
Get-Icon "delapouite" "coins" "stats" "taler"

# Items (Resources)
Get-Icon "delapouite" "wood-pile" "items" "holz"
Get-Icon "lorc" "stone-block" "items" "stein"
Get-Icon "lorc" "metal-bar" "items" "eisen"
Get-Icon "delapouite" "powder-bag" "items" "beton"
Get-Icon "delapouite" "coal-wagon" "items" "kohle"

# Items (Consumables)
Get-Icon "lorc" "bandage-roll" "items" "verband"
Get-Icon "delapouite" "canned-fish" "items" "konserve"
Get-Icon "delapouite" "water-bottle" "items" "wasser"
Get-Icon "lorc" "mushroom-gills" "items" "pilze"
Get-Icon "lorc" "meat" "items" "fleisch"
Get-Icon "delapouite" "coffee-cup" "items" "kaffee"
Get-Icon "guard13007" "soda-can" "items" "energy_drink"
Get-Icon "lorc" "book-cover" "items" "buch_ueberleben" # Buch: Überleben

# Items (Weapons/Tools)
Get-Icon "lorc" "fire-axe" "items" "axt"
Get-Icon "lorc" "barbed-spear" "items" "speer"
Get-Icon "lorc" "bowie-knife" "items" "messer"
Get-Icon "lorc" "machete" "items" "machete"
Get-Icon "delapouite" "kevlar-vest" "items" "kevlar_weste"
Get-Icon "delapouite" "medical-pack" "items" "medikit"

# Buildings
Get-Icon "delapouite" "brick-wall" "buildings" "wall"
Get-Icon "delapouite" "wooden-crate" "buildings" "storage"
Get-Icon "lorc" "locked-chest" "buildings" "vault"
Get-Icon "delapouite" "family-house" "buildings" "hq"
Get-Icon "lorc" "wolf-trap" "buildings" "falle"
Get-Icon "lorc" "sentry-gun" "buildings" "geschuetz"

echo "Done!"
