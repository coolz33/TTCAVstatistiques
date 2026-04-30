<?php
require_once 'config.php';
require_once LIB_DIR . '/Database.php';

$clubId = $_GET['clubId'] ?? FFTT_CLUB_ID;
$db = Database::getInstance();

// Récupérer les infos du club
$stmt = $db->prepare("SELECT * FROM clubs WHERE numero = ?");
$stmt->execute([$clubId]);
$club = $stmt->fetch();
$clubName = $club ? $club['nom'] : 'Club ' . $clubId;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Synchronisation - <?php echo htmlspecialchars($clubName); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="dark-theme d-flex align-items-center justify-content-center" style="min-height: 100vh;">
    <div class="container text-center">
        <div class="loading-card bg-dark p-5 rounded-4 shadow-lg border border-secondary" style="max-width: 600px; margin: 0 auto;">
            <div class="mb-4">
                <i class="fas fa-sync fa-spin fa-3x text-primary mb-3"></i>
                <h2 class="fw-bold text-white">Synchronisation en cours</h2>
                <p class="text-muted">Récupération des données depuis l'API Smartping...</p>
            </div>

            <div class="progress mb-3" style="height: 25px; background: #1a202c; border-radius: 50px;">
                <div id="progressBar" class="progress-bar progress-bar-striped progress-bar-animated bg-primary" 
                     role="progressbar" style="width: 0%; border-radius: 50px;" 
                     aria-valuenow="0" aria-valuemin="0" aria-valuemax="100">0%</div>
            </div>

            <div class="d-flex justify-content-between text-muted small mb-4">
                <span id="currentStatus">Initialisation...</span>
                <span id="progressText">0 / 0 joueurs</span>
            </div>

            <div id="logArea" class="text-start bg-black p-3 rounded-3 small text-success overflow-auto" 
                 style="height: 120px; font-family: monospace; border: 1px solid #2d3748;">
                <div>[SYSTEM] Connexion à l'API FFTT...</div>
            </div>
        </div>
    </div>

    <script>
        const clubId = "<?php echo $clubId; ?>";
        let isSyncing = false;

        function startSync() {
            if (isSyncing) return;
            isSyncing = true;
            
            // On lance la synchro en arrière plan (via fetch)
            fetch('sync.php?clubId=' + clubId)
                .then(response => {
                    console.log("Synchro terminée");
                })
                .catch(err => {
                    console.error("Erreur synchro", err);
                });

            // On commence à poller la progression
            pollProgress();
        }

        function pollProgress() {
            fetch('ajax.php?action=getSyncProgress&clubId=' + clubId)
                .then(response => response.json())
                .then(data => {
                    if (data.error) return;

                    const percent = data.total > 0 ? Math.round((data.current / data.total) * 100) : 0;
                    document.getElementById('progressBar').style.width = percent + '%';
                    document.getElementById('progressBar').textContent = percent + '%';
                    document.getElementById('progressText').textContent = data.current + ' / ' + data.total + ' joueurs';
                    
                    if (data.status === 'syncing') {
                        document.getElementById('currentStatus').textContent = "Mise à jour des joueurs...";
                        if (data.last_item) {
                            const logArea = document.getElementById('logArea');
                            const lastLog = logArea.lastElementChild;
                            const newLogText = `[SYNC] Traitement de ${data.last_item}...`;
                            if (!lastLog || lastLog.textContent !== newLogText) {
                                const div = document.createElement('div');
                                div.textContent = newLogText;
                                logArea.appendChild(div);
                                logArea.scrollTop = logArea.scrollHeight;
                            }
                        }
                        setTimeout(pollProgress, 1000);
                    } else if (data.status === 'idle' && data.current === data.total && data.total > 0) {
                        document.getElementById('currentStatus').textContent = "Terminé ! Redirection...";
                        setTimeout(() => window.location.href = 'index.php', 1500);
                    } else {
                        setTimeout(pollProgress, 1000);
                    }
                });
        }

        // Démarrage
        startSync();
    </script>
</body>
</html>
