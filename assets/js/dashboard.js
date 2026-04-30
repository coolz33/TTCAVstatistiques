let charts = {};
// On récupère le tri stocké ou on utilise le défaut (VIRTUEL desc)
let currentSort = JSON.parse(localStorage.getItem('ttcav_sort')) || { column: 6, direction: 'desc' };

document.addEventListener('DOMContentLoaded', function() {
    initTheme();
    initTableEvents();
    initSearch();
    initSyncAll();
    initAvatarUpload();
    initAvatarSizeSelector();
    initAvatarHoverPreview();
    initMobileSort();
    
    // Restauration de l'état (Tri + Recherche + Détails)
    restoreState();
    
    // Si premier lancement sans historique, tri par défaut
    if (!localStorage.getItem('ttcav_sort') && !localStorage.getItem('ttcav_state')) {
        sortTable(6, 'desc');
    }
    
    const tbody = document.querySelector('.players-table tbody');
    if (tbody) {
        tbody.addEventListener('click', function(e) {
            const row = e.target.closest('.player-row');
            const isAction = e.target.closest('.refresh-icon') || e.target.closest('.player-avatar');
            
            if (row && !isAction) {
                toggleDetails(row.dataset.licence);
            }
        });
    }
    // Attacher le bouton toggle PC
    const toggleBtn = document.getElementById('themeToggleBtn');
    if (toggleBtn) toggleBtn.addEventListener('click', toggleTheme);
});

/* ===== GESTION DU THÈME ===== */
function initTheme() {
    const saved = localStorage.getItem('ttcav_theme') || 'dark';
    applyTheme(saved);
}

function toggleTheme() {
    const body = document.body;
    const isDark = body.classList.contains('dark-theme');
    applyTheme(isDark ? 'light' : 'dark');
    localStorage.setItem('ttcav_theme', isDark ? 'light' : 'dark');
}

function applyTheme(theme) {
    const body = document.body;
    body.classList.remove('dark-theme', 'light-theme');
    body.classList.add(theme + '-theme');

    const icon = theme === 'light' ? 'fa-moon' : 'fa-sun';
    const title = theme === 'light' ? 'Passer en thème sombre' : 'Passer en thème clair';

    // Mettre à jour les deux boutons (PC + Mobile)
    ['themeToggleBtn', 'themeToggleBtnMobile'].forEach(id => {
        const btn = document.getElementById(id);
        if (!btn) return;
        const i = btn.querySelector('i');
        if (i) {
            i.className = 'fas ' + icon;
        }
        btn.title = title;
    });
}

function saveState() {
    if (window._isRestoring) return;
    const searchInput = document.getElementById('playerSearch');
    const expanded = Array.from(document.querySelectorAll('.details-row:not(.hidden-row)'))
                          .map(row => row.id.replace('details-', ''));
    
    const state = {
        search: searchInput ? searchInput.value : '',
        expanded: expanded,
        sort: currentSort,
        avatarSize: localStorage.getItem('ttcav_avatar_size') || 'md'
    };
    localStorage.setItem('ttcav_state', JSON.stringify(state));
}

function restoreState() {
    const saved = JSON.parse(localStorage.getItem('ttcav_state'));
    if (!saved) {
        sortTable(currentSort.column, currentSort.direction);
        return;
    }

    // Bloquer les saveState automatiques pendant la restauration
    window._isRestoring = true;

    // 1. Restaurer le tri
    currentSort = saved.sort;
    sortTable(currentSort.column, currentSort.direction);

    // 2. Restaurer la recherche
    const searchInput = document.getElementById('playerSearch');
    if (searchInput && saved.search) {
        searchInput.value = saved.search;
        // Déclencher le filtrage manuellement
        const event = new Event('input', { bubbles: true });
        searchInput.dispatchEvent(event);
    }

    // 3. Restaurer la taille des avatars
    if (saved.avatarSize) {
        setAvatarSize(saved.avatarSize);
    }

    // 4. Restaurer les volets ouverts
    if (saved.expanded && saved.expanded.length > 0) {
        saved.expanded.forEach(licence => {
            toggleDetails(licence, true);
        });
    }

    window._isRestoring = false;
}

function initTableEvents() {
    const table = document.querySelector('.players-table');
    if (!table) return;

    table.querySelectorAll('th').forEach((th, index) => {
        if (th.classList.contains('col-player') || th.classList.contains('col-stat')) {
            th.style.cursor = 'pointer';
            th.addEventListener('click', () => sortTable(index));
        }
    });
}

function initSearch() {
    const searchInput = document.getElementById('playerSearch');
    const clearBtn = document.getElementById('clearSearch');
    if (!searchInput) return;

    searchInput.addEventListener('input', function() {
        const term = this.value.toLowerCase();
        if (clearBtn) clearBtn.style.display = term ? 'block' : 'none';
        
        const playerRows = document.querySelectorAll('.player-row');
        const playerCountSpan = document.getElementById('playerCount');
        let visibleCount = 0;

        playerRows.forEach(row => {
            const nom = row.querySelector('.player-nom') ? row.querySelector('.player-nom').textContent.toLowerCase() : '';
            const prenom = row.querySelector('.player-prenom') ? row.querySelector('.player-prenom').textContent.toLowerCase() : '';
            const licence = row.querySelector('.player-licence') ? row.querySelector('.player-licence').textContent.toLowerCase() : '';
            const detailsRow = document.getElementById('details-' + row.dataset.licence);

            if (nom.includes(term) || prenom.includes(term) || licence.includes(term)) {
                row.classList.remove('hidden');
                visibleCount++;
                
                // Mettre à jour le rang dans les DEUX emplacements (PC et Mobile)
                const pcRank = row.querySelector('.col-rank .rank-number');
                if (pcRank) pcRank.textContent = visibleCount;
                
                const mobileRank = row.querySelector('.mobile-rank-wrapper .rank-number');
                if (mobileRank) mobileRank.textContent = visibleCount;
            } else {
                row.classList.add('hidden');
                if (detailsRow) detailsRow.classList.add('hidden-row');
            }
        });

        if (playerCountSpan) playerCountSpan.textContent = visibleCount;
        saveState(); // Sauvegarder après filtrage
    });

    if (clearBtn) {
        clearBtn.addEventListener('click', function() {
            searchInput.value = '';
            searchInput.dispatchEvent(new Event('input'));
            searchInput.focus();
        });
    }
}

function sortTable(columnIndex, forceDir = null) {
    const table = document.querySelector('.players-table');
    const tbody = table.querySelector('tbody');
    const rows = Array.from(tbody.querySelectorAll('.player-row'));
    
    let direction = forceDir;
    if (!direction) {
        direction = 'desc';
        if (currentSort.column === columnIndex) {
            direction = (currentSort.direction === 'desc') ? 'asc' : 'desc';
        } else if (columnIndex === 1) {
            direction = 'asc';
        }
    }
    
    currentSort = { column: columnIndex, direction: direction };

    // Synchroniser les sélecteurs mobiles
    const colSel = document.getElementById('mobileSortCol');
    const dirSel = document.getElementById('mobileSortDir');
    if (colSel) colSel.value = columnIndex;
    if (dirSel) dirSel.value = direction;

    rows.sort((a, b) => {
        const cellA = a.cells[columnIndex];
        const cellB = b.cells[columnIndex];
        
        let valA, valB;
        const mainA = cellA.querySelector('.main-val');
        const mainB = cellB.querySelector('.main-val');

        if (mainA && mainB) {
            valA = parseFloat(mainA.innerText) || 0;
            valB = parseFloat(mainB.innerText) || 0;
        } else {
            const textA = cellA.innerText.trim();
            const textB = cellB.innerText.trim();
            const numA = textA.match(/-?\d+(\.\d+)?/);
            const numB = textB.match(/-?\d+(\.\d+)?/);
            
            if (numA && numB && columnIndex > 1) { 
                valA = parseFloat(numA[0]);
                valB = parseFloat(numB[0]);
            } else {
                valA = textA.toLowerCase();
                valB = textB.toLowerCase();
            }
        }

        if (typeof valA === 'number' && typeof valB === 'number') {
            return direction === 'asc' ? valA - valB : valB - valA;
        }
        return direction === 'asc' ? valA.toString().localeCompare(valB.toString()) : valB.toString().localeCompare(valA.toString());
    });

    const totalVisible = rows.filter(r => !r.classList.contains('hidden')).length;
    let count = 0;
    
    rows.forEach(row => {
        const detailsRow = document.getElementById('details-' + row.dataset.licence);
        tbody.appendChild(row);
        if (detailsRow) tbody.appendChild(detailsRow);
        
        if (!row.classList.contains('hidden')) {
            count++;
            let displayRank;
            if (direction === 'asc' && columnIndex > 1) {
                displayRank = totalVisible - count + 1;
            } else {
                displayRank = count;
            }
            // Mettre à jour le rang dans les DEUX emplacements (PC et Mobile)
            const pcRank = row.querySelector('.col-rank .rank-number');
            if (pcRank) pcRank.textContent = displayRank;
            
            const mobileRank = row.querySelector('.mobile-rank-wrapper .rank-number');
            if (mobileRank) mobileRank.textContent = displayRank;
        }
    });

    table.querySelectorAll('th i').forEach(i => i.className = 'fas fa-sort');
    const icon = table.querySelectorAll('th')[columnIndex].querySelector('i');
    if (icon) icon.className = direction === 'asc' ? 'fas fa-sort-up' : 'fas fa-sort-down';
    
    saveState(); // Sauvegarder après tri
}

function initSyncAll() {
    const syncHandler = async function() {
        if (!confirm('Synchroniser tout le club ?')) return;
        
        const rows = Array.from(document.querySelectorAll('.player-row'));
        const container = document.getElementById('syncProgressContainer');
        const progressBar = document.getElementById('syncProgressBar');
        const progressText = document.getElementById('syncProgressText');
        
        container.classList.remove('hidden');
        document.querySelectorAll('#btnSyncAllPC, #btnSyncAllMobile').forEach(b => b.disabled = true);
        
        let completed = 0;
        const batchSize = 5;
        for (let i = 0; i < rows.length; i += batchSize) {
            const batch = rows.slice(i, i + batchSize);
            await Promise.all(batch.map(async (row) => {
                const lic = row.dataset.licence;
                try {
                    await fetch(`ajax.php?action=fullSync&licence=${lic}`);
                } catch (e) {}
                completed++;
                const p = (completed / rows.length) * 100;
                progressBar.style.width = p + '%';
                progressText.textContent = `${completed}/${rows.length}`;
            }));
        }
        
        location.reload();
    };

    // Attacher à PC et Mobile
    document.querySelectorAll('#btnSyncAllPC, #btnSyncAllMobile').forEach(btn => {
        if (btn) btn.addEventListener('click', syncHandler);
    });
}

function toggleDetails(licence, forceOpen = false) {
    const detailRow = document.getElementById('details-' + licence);
    const playerRow = document.querySelector(`.player-row[data-licence="${licence}"]`);
    if (!detailRow) return;
    
    const isHidden = detailRow.classList.contains('hidden-row');
    
    if (forceOpen || isHidden) {
        detailRow.classList.remove('hidden-row');
        if (playerRow) playerRow.classList.add('expanded');
        loadChart(licence);
        loadMatches(licence);
    } else {
        detailRow.classList.add('hidden-row');
        if (playerRow) playerRow.classList.remove('expanded');
    }
    saveState(); // Sauvegarder l'état (volets ouverts)
}

function loadChart(licence) {
    const canvas = document.getElementById('chart-' + licence);
    if (!canvas || charts[licence]) return;
    const ctx = canvas.getContext('2d');

    const ts = new Date().getTime();
    fetch(`ajax.php?action=getHistory&licence=${licence}&t=${ts}`)
        .then(r => r.json())
        .then(data => {
            if (!data || data.error || data.length === 0) {
                console.warn('History data empty or error', data);
                return;
            }

            // On s'assure que le plus ancien est à gauche. 
            // Si l'API renvoie du plus récent au plus ancien, on inverse.
            // Habituellement l'API FFTT renvoie le plus récent en premier.
            try {
                const firstYearMatch = data[0].saison.match(/\d+/);
                const lastYearMatch = data[data.length-1].saison.match(/\d+/);
                const firstYear = firstYearMatch ? parseInt(firstYearMatch[0]) : 0;
                const lastYear = lastYearMatch ? parseInt(lastYearMatch[0]) : 0;
                if (firstYear > lastYear) {
                    data.reverse();
                }
            } catch (e) {
                console.error('Error parsing history seasons', e);
            }
            
            // Récupérer les points mensuels et virtuels depuis le tableau principal
            const playerRow = document.querySelector(`.player-row[data-licence="${licence}"]`);
            let monthlyPoints = null;
            let virtualPoints = null;

            if (playerRow) {
                const mCell = playerRow.cells[5]; // Index 5 : Mensuel
                const vCell = playerRow.cells[6]; // Index 6 : Virtuel
                const mVal = mCell.querySelector('.main-val');
                const vVal = vCell.querySelector('.main-val');
                if (mVal) monthlyPoints = parseFloat(mVal.innerText);
                if (vVal) virtualPoints = parseFloat(vVal.innerText);
            }

            // Labels : Afficher l'année un point sur deux pour la lisibilité
            const labels = data.map((d, i) => {
                if (i % 2 === 0) {
                    return d.saison.split(' ')[1] || d.saison;
                }
                return '';
            });
            const points = data.map(d => d.points);
            
            // On ajoute les nouveaux labels
            labels.push('Mensuel');
            labels.push('Virtuel');
            
            // Dataset 1 : Historique officiel (ligne pleine)
            const datasets = [{
                label: 'Officiel',
                data: points,
                borderColor: '#f56565',
                backgroundColor: 'rgba(245, 101, 101, 0.1)',
                borderWidth: 3,
                pointBackgroundColor: '#f56565',
                pointRadius: 4,
                tension: 0.1,
                fill: true
            }];

            // Dataset 2 : Projection vers Mensuel (pointillé jaune)
            if (monthlyPoints) {
                const monthlyData = new Array(points.length - 1).fill(null);
                monthlyData.push(points[points.length - 1]); // Part du dernier officiel
                monthlyData.push(monthlyPoints); // Vers mensuel
                monthlyData.push(null); // Pas de point sur virtuel

                datasets.push({
                    label: 'Mensuel',
                    data: monthlyData,
                    borderColor: '#ecc94b',
                    backgroundColor: 'rgba(236, 201, 75, 0.1)',
                    borderWidth: 2,
                    borderDash: [5, 5],
                    pointBackgroundColor: '#ecc94b',
                    pointRadius: 5,
                    tension: 0,
                    fill: true
                });
            }

            // Dataset 3 : Projection vers Virtuel (pointillé vert/jaune)
            if (virtualPoints) {
                const virtualData = new Array(points.length).fill(null);
                virtualData.push(monthlyPoints || points[points.length - 1]); // Part du mensuel (ou dernier officiel)
                virtualData.push(virtualPoints); // Vers virtuel

                datasets.push({
                    label: 'Virtuel',
                    data: virtualData,
                    borderColor: '#48bb78',
                    backgroundColor: 'rgba(72, 187, 120, 0.1)',
                    borderWidth: 2,
                    borderDash: [3, 3],
                    pointBackgroundColor: '#48bb78',
                    pointRadius: 7,
                    pointStyle: 'star',
                    tension: 0,
                    fill: true
                });
            }

            charts[licence] = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: datasets
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: { grid: { color: 'rgba(255,255,255,0.05)' }, ticks: { color: '#a0aec0' } },
                        x: { 
                            grid: { 
                                display: true,
                                color: (context) => {
                                    if (context.tick && context.tick.label !== '') {
                                        return 'rgba(255, 255, 255, 0.1)';
                                    }
                                    return 'transparent';
                                },
                                drawBorder: false,
                                drawTicks: false
                            }, 
                            ticks: { 
                                color: '#a0aec0',
                                maxRotation: 45,
                                minRotation: 45,
                                autoSkip: false
                            } 
                        }
                    },
                    plugins: { 
                        legend: { display: false },
                        tooltip: {
                            backgroundColor: '#2d3748',
                            callbacks: {
                                title: (items) => {
                                    const idx = items[0].dataIndex;
                                    if (idx < data.length) {
                                        return `Saison ${data[idx].saison} - Phase ${data[idx].phase}`;
                                    }
                                    if (idx === data.length) return 'Points Mensuels (FFTT)';
                                    return 'Points Virtuels (Live)';
                                },
                                label: (item) => `${Math.round(item.raw)} points`
                            }
                        },
                        zoom: {
                            zoom: {
                                wheel: { enabled: true },
                                pinch: { enabled: true },
                                mode: 'x',
                            },
                            pan: {
                                enabled: true,
                                mode: 'x',
                            }
                        }
                    }
                }
            });
        })
        .catch(err => {
            console.error('Error loading chart:', err);
        });
}

function loadMatches(licence) {
    const detailRow = document.getElementById('details-' + licence);
    if (!detailRow) return;
    const container = detailRow.querySelector('.matches-container');
    if (!container) return;

    const ts = new Date().getTime();
    fetch(`ajax.php?action=getMatches&licence=${licence}&t=${ts}`)
        .then(r => r.json())
        .then(data => {
            if (!Array.isArray(data) || data.length === 0) {
                container.innerHTML = '<div class="text-center p-3 text-muted">Aucun match récent.</div>';
                return;
            }

            let html = '<div class="matches-list">';
            let currentGroup = '';
            
            const getEpreuveName = (code) => {
                const map = {
                    '1': 'Championnat par Équipes',
                    'I': 'Critérium Fédéral',
                    'F': 'Féminines',
                    'P': 'Coupe de Paris',
                    'T': 'Tournoi',
                    'J': 'Circuit Jeunes',
                    'C': 'Circuit Jeunes'
                };
                return map[code] || code;
            };

            const getEpreuveCoef = (code) => {
                const text = code.toLowerCase();
                if (text.includes('criterium') || text.includes('critéri')) return '1.5';
                if (text.includes('jeunes')) return '0.5';
                if (text.includes('championnat') || text.includes('equipe')) return '1.0';
                if (text.includes('tournoi')) return '0.5';
                
                const map = { '1': '1.0', 'I': '1.5', 'F': '1.0', 'P': '1.0', 'J': '0.5', 'C': '0.5', '#': '0.5' };
                return map[code] || '0.5';
            };
            const formatPts = (n) => {
                const val = Number(n);
                if (Number.isInteger(val)) return val.toString();
                return val.toFixed(3).replace(/\.?0+$/, "");
            };

            // Pré-calcul des totaux par groupe
            const groupStats = {};
            let liveCount = 0;
            let validatedCount = 0;

            data.forEach(m => {
                const dateStr = new Date(m.date_match).toLocaleDateString('fr-FR');
                const epreuveFull = getEpreuveName(m.epreuve);
                const groupKey = `${dateStr} - ${epreuveFull}`;
                
                if (!groupStats[groupKey]) {
                    groupStats[groupKey] = { won: 0, lost: 0, total: 0 };
                }
                const pts = (m.points_resultat !== 0 && m.points_resultat !== null) ? m.points_resultat : m.points_calcules;
                if (pts > 0) groupStats[groupKey].won += pts;
                else groupStats[groupKey].lost += pts;
                groupStats[groupKey].total += pts;

                if (m.is_validated == 0) liveCount++;
                else validatedCount++;
            });
            
            let currentValidationState = null;
            data.forEach(m => {
                const dateStr = new Date(m.date_match).toLocaleDateString('fr-FR');
                const epreuveFull = getEpreuveName(m.epreuve);
                const coef = m.coefficient ? m.coefficient : getEpreuveCoef(m.epreuve);
                const groupKey = `${dateStr} - ${epreuveFull}`;
                const isLive = (m.is_validated == 0);

                if (currentValidationState !== isLive) {
                    currentValidationState = isLive;
                    if (isLive) {
                        html += `<div class="validation-separator live">Matchs non validés (${liveCount})</div>`;
                    } else {
                        html += `<div class="validation-separator validated">Matchs validés (${validatedCount})</div>`;
                    }
                    currentGroup = ''; // Forcer l'affichage de la date après le séparateur
                }
                
                if (groupKey !== currentGroup) {
                    currentGroup = groupKey;
                    const stats = groupStats[groupKey];
                    const wonStr = `<span style="color: var(--plus-color); font-weight: bold;">+${formatPts(stats.won)}</span>`;
                    const lostStr = `<span style="color: var(--minus-color); font-weight: bold;">${formatPts(stats.lost)}</span>`;
                    const totalVal = stats.total;
                    const totalColor = totalVal > 0 ? 'var(--plus-color)' : (totalVal < 0 ? 'var(--minus-color)' : '#9E9E9E');
                    const totalSign = totalVal > 0 ? '+' : '';
                    const totalStr = `<span style="color: ${totalColor}; font-weight: bold;">${totalSign}${formatPts(totalVal)}</span>`;
                    
                    html += `<div class="match-date-group">
                                <div>
                                    <i class="far fa-calendar-alt me-1"></i> ${groupKey}
                                    <span class="badge-coef">COEFF x${coef}</span>
                                </div>
                                <div style="font-size: 0.85rem; background: rgba(0,0,0,0.2); padding: 2px 8px; border-radius: 4px;">
                                    Bilan : ${wonStr} | ${lostStr} = ${totalStr}
                                </div>
                             </div>`;
                }

                const isWin = m.victoire_defaite === 'V';
                const statusClass = isWin ? 'win' : 'loss';
                const pts = (m.points_resultat !== 0 && m.points_resultat !== null) ? m.points_resultat : m.points_calcules;
                const ptsLabel = (pts > 0 ? '+' : '') + formatPts(pts);
                
                let liveBadge = '';
                if (isLive) {
                    liveBadge = `<span class="badge-virtual">VIRTUEL</span>`;
                }

                let setsHtml = '';
                if (m.score_detail) {
                    const sets = m.score_detail.trim().split(/\s+/);
                    setsHtml = '<div class="sets-container">';
                    sets.forEach(s => {
                        const score = parseInt(s);
                        if (isNaN(score)) return;
                        const loserScore = Math.abs(score);
                        const winnerScore = loserScore < 10 ? 11 : loserScore + 2;
                        const setWon = score > 0;
                        const displayWon = (m.score_ja > m.score_jb) ? setWon : !setWon;
                        setsHtml += `
                            <div class="set-box">
                                <div class="set-top ${displayWon ? 'bg-success' : 'bg-dark text-muted'}">${displayWon ? winnerScore : loserScore}</div>
                                <div class="set-bottom ${!displayWon ? 'bg-danger' : 'bg-dark text-muted'}">${!displayWon ? winnerScore : loserScore}</div>
                            </div>`;
                    });
                    setsHtml += '</div>';
                }

                html += `
                <div class="match-card ${statusClass} py-1 px-2 mb-1 d-flex align-items-center">
                    <div class="match-points-badge ${statusClass}">${ptsLabel}</div>
                    <div class="match-info flex-grow-1 ms-2">
                        <div class="match-opponent d-flex justify-content-between align-items-center">
                            <div>
                                <span class="text-warning small me-1">${Number(m.adversaire_points).toFixed(1)}</span>
                                ${liveBadge}${m.adversaire_nom}
                            </div>
                            <div class="d-flex align-items-center gap-2">
                                ${setsHtml}
                                <span class="match-result-badge ${isWin ? 'bg-success' : 'bg-danger'}">${isWin ? 'Victoire' : 'Défaite'}</span>
                            </div>
                        </div>
                        <div class="match-meta d-flex gap-2 mt-1">
                             <span class="small text-muted"><i class="fas fa-hashtag me-1"></i>Match n°${m.idpartie || 'N/A'}</span>
                        </div>
                    </div>
                </div>`;
            });
            container.innerHTML = html + '</div>';
        })
        .catch(err => {
            console.error('Error loading matches:', err);
            container.innerHTML = '<div class="text-center p-3 text-danger">Erreur lors du chargement des matchs.</div>';
        });
}

function formatDate(dateStr) {
    const d = new Date(dateStr);
    return d.toLocaleDateString('fr-FR', { month: 'short', year: 'numeric' });
}

function syncSinglePlayer(licence) {
    const icon = event.target.closest('.refresh-icon-mini');
    if (icon) icon.classList.add('fa-spin');
    
    // On lance la synchro complète (Profil + Officiel + Live + Virtuel)
    fetch(`ajax.php?action=syncPlayer&licence=${licence}`)
        .then(r => r.json())
        .then(data => {
            if (data.error) throw new Error(data.error);
            saveState();
            location.reload();
        })
        .catch(err => {
            console.error('Sync failed:', err);
            if (icon) icon.classList.remove('fa-spin');
            alert('Erreur lors de la synchronisation : ' + err.message);
        });
}

let currentUploadLicence = null;

function initAvatarUpload() {
    const input = document.getElementById('avatarInput');
    if (!input) return;

    input.addEventListener('change', function() {
        if (!this.files || !this.files[0] || !currentUploadLicence) return;

        const formData = new FormData();
        formData.append('avatar', this.files[0]);
        formData.append('licence', currentUploadLicence);

        const avatarDiv = document.querySelector(`.player-row[data-licence="${currentUploadLicence}"] .player-avatar`);
        const originalHtml = avatarDiv.innerHTML;
        avatarDiv.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

        fetch('ajax.php?action=uploadAvatar', {
            method: 'POST',
            body: formData
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                avatarDiv.setAttribute('oncontextmenu', `event.preventDefault(); event.stopPropagation(); const img = this.querySelector('img'); if(img) triggerAvatarRecenter(img, '${currentUploadLicence}')`);
                avatarDiv.innerHTML = `<div class="avatar-crop-container">
                                        <img src="assets/avatars/${data.avatar}" class="avatar-img" style="width: 100%; left: 0%; top: 0%;">
                                      </div>
                                      <div class="recenter-hint"><i class="fas fa-arrows-alt"></i> Clic droit pour recentrer</div>`;
            } else {
                avatarDiv.innerHTML = originalHtml;
                alert('Erreur : ' + data.error);
            }
        })
        .catch(err => {
            avatarDiv.innerHTML = originalHtml;
            alert('Erreur réseau');
        });
    });
}

function triggerAvatarUpload(licence) {
    currentUploadLicence = licence;
    document.getElementById('avatarInput').click();
}

function initAvatarSizeSelector() {
    // Attacher aux deux conteneurs : PC et Mobile
    ['avatarSizeSelectorPC', 'avatarSizeSelectorMobile'].forEach(id => {
        const container = document.getElementById(id);
        if (!container) return;
        container.addEventListener('click', (e) => {
            const btn = e.target.closest('[data-size]');
            if (!btn) return;
            setAvatarSize(btn.dataset.size);
            saveState();
        });
    });
}

function showAvatarBig(el) {
    const avatar = el.closest('.player-avatar');
    const img = avatar.querySelector('.avatar-img');
    if (!img) return;

    const overlay = document.createElement('div');
    overlay.className = 'avatar-big-overlay';
    overlay.innerHTML = `<img src="${img.src}" class="avatar-big-img">`;
    overlay.onclick = () => overlay.remove();
    document.body.appendChild(overlay);
}

function setAvatarSize(size) {
    const avatars = document.querySelectorAll('.player-avatar');
    avatars.forEach(av => {
        av.classList.remove('avatar-sm', 'avatar-md', 'avatar-lg');
        av.classList.add('avatar-' + size);
    });

    // Update active button (PC + Mobile)
    ['avatarSizeSelectorPC', 'avatarSizeSelectorMobile'].forEach(id => {
        const container = document.getElementById(id);
        if (!container) return;
        container.querySelectorAll('.btn').forEach(btn => {
            btn.classList.toggle('active', btn.dataset.size === size);
            btn.classList.toggle('btn-secondary', btn.dataset.size === size);
            btn.classList.toggle('btn-outline-secondary', btn.dataset.size !== size);
        });
    });
    
    localStorage.setItem('ttcav_avatar_size', size);
}

function initMobileSort() {
    const colSel = document.getElementById('mobileSortCol');
    const dirSel = document.getElementById('mobileSortDir');
    if (!colSel || !dirSel) return;

    const apply = () => {
        const col = parseInt(colSel.value);
        const dir = dirSel.value;
        currentSort = { column: col, direction: dir };
        localStorage.setItem('ttcav_sort', JSON.stringify(currentSort));
        sortTable(col, dir);
    };

    colSel.addEventListener('change', apply);
    dirSel.addEventListener('change', apply);
}

function initAvatarHoverPreview() {
    let previewEl = null;

    document.addEventListener('mouseover', (e) => {
        const avatar = e.target.closest('.player-avatar');
        if (!avatar) return;

        const img = avatar.querySelector('.avatar-img');
        if (!img || img.classList.contains('repositioning')) return;

        if (previewEl) previewEl.remove();

        previewEl = document.createElement('img');
        previewEl.src = img.src;
        previewEl.className = 'avatar-float-preview';
        document.body.appendChild(previewEl);

        const updatePos = (moveEvent) => {
            if (!previewEl) return;
            let left = moveEvent.clientX + 20;
            let top = moveEvent.clientY - 150;
            
            // Éviter de sortir de l'écran
            if (left + 500 > window.innerWidth) left = moveEvent.clientX - 520;
            if (top < 0) top = 10;

            previewEl.style.left = left + 'px';
            previewEl.style.top = top + 'px';
        };

        avatar.addEventListener('mousemove', updatePos);
        avatar._previewUpdatePos = updatePos;
    });

    document.addEventListener('mouseout', (e) => {
        const avatar = e.target.closest('.player-avatar');
        if (!avatar || !previewEl) return;

        // On vérifie si on sort vraiment de l'avatar (pas vers un enfant)
        if (!e.relatedTarget || !avatar.contains(e.relatedTarget)) {
            previewEl.remove();
            previewEl = null;
            avatar.removeEventListener('mousemove', avatar._previewUpdatePos);
        }
    });
}

function triggerAvatarRecenter(img, licence) {
    const overlay = document.createElement('div');
    overlay.className = 'recenter-overlay';
    
    // On récupère les valeurs actuelles (basées sur les % de la vignette)
    const initialZoom = parseFloat(img.style.width) / 100 || 1.0;
    const initialLeftPct = parseFloat(img.style.left) || 0;
    const initialTopPct = parseFloat(img.style.top) || 0;

    overlay.innerHTML = `
        <div class="recenter-container">
            <img src="${img.src}" class="recenter-image" id="recenterImg">
            <div class="recenter-selector" id="recenterSelector">
                <div class="recenter-handle" id="recenterHandle"></div>
            </div>
        </div>
        <div class="recenter-actions">
            <button class="btn-recenter-save" id="btnSaveRecenter">Valider le cadrage</button>
        </div>
    `;

    document.body.appendChild(overlay);

    const fullImg = document.getElementById('recenterImg');
    const selector = document.getElementById('recenterSelector');
    const handle = document.getElementById('recenterHandle');

    fullImg.onload = () => {
        const imgWidth = fullImg.clientWidth;
        const imgHeight = fullImg.clientHeight;
        
        // On calcule la taille du carré dans l'outil
        let side = imgWidth / initialZoom;
        
        // Sécurité si side est trop grand ou trop petit
        if (side > Math.min(imgWidth, imgHeight)) side = Math.min(imgWidth, imgHeight) * 0.8;
        if (side < 20) side = 50;

        selector.style.width = side + 'px';
        selector.style.height = side + 'px';

        // Position du carré : on inverse le calcul du dashboard
        let left = -(initialLeftPct / 100) * side;
        let top = -(initialTopPct / 100) * side;

        // Bornage
        left = Math.max(0, Math.min(imgWidth - side, left));
        top = Math.max(0, Math.min(imgHeight - side, top));

        selector.style.left = left + 'px';
        selector.style.top = top + 'px';

        let isDragging = false;
        let isResizing = false;
        let startX, startY, startLeft, startTop, startSide;

        selector.onmousedown = (e) => {
            if (e.target === handle) return;
            isDragging = true;
            startX = e.clientX;
            startY = e.clientY;
            startLeft = selector.offsetLeft;
            startTop = selector.offsetTop;
            e.preventDefault();
        };

        handle.onmousedown = (e) => {
            isResizing = true;
            startX = e.clientX;
            startY = e.clientY;
            startSide = selector.offsetWidth;
            e.preventDefault();
            e.stopPropagation();
        };

        const moveHandler = (e) => {
            if (isDragging) {
                let newLeft = startLeft + (e.clientX - startX);
                let newTop = startTop + (e.clientY - startY);
                newLeft = Math.max(0, Math.min(imgWidth - selector.offsetWidth, newLeft));
                newTop = Math.max(0, Math.min(imgHeight - selector.offsetHeight, newTop));
                selector.style.left = newLeft + 'px';
                selector.style.top = newTop + 'px';
            } else if (isResizing) {
                const delta = Math.max(e.clientX - startX, e.clientY - startY);
                let newSide = startSide + delta;
                newSide = Math.max(30, Math.min(newSide, imgWidth - selector.offsetLeft, imgHeight - selector.offsetTop));
                selector.style.width = newSide + 'px';
                selector.style.height = newSide + 'px';
            }
        };

        const stopHandler = () => {
            isDragging = false;
            isResizing = false;
        };

        window.addEventListener('mousemove', moveHandler);
        window.addEventListener('mouseup', stopHandler);

        document.getElementById('btnSaveRecenter').onclick = () => {
            const side = selector.offsetWidth;
            const zoom = imgWidth / side;
            const leftPct = -(selector.offsetLeft / side) * 100;
            const topPct = -(selector.offsetTop / side) * 100;

            img.style.width = (zoom * 100) + '%';
            img.style.left = leftPct + '%';
            img.style.top = topPct + '%';
            
            const formData = new FormData();
            formData.append('licence', licence);
            formData.append('zoom', zoom.toFixed(3));
            formData.append('x', leftPct.toFixed(2));
            formData.append('y', topPct.toFixed(2));
            fetch('ajax.php?action=updateAvatarPos', { method: 'POST', body: formData });

            document.body.removeChild(overlay);
            window.removeEventListener('mousemove', moveHandler);
            window.removeEventListener('mouseup', stopHandler);
        };

        overlay.onclick = (e) => {
            if (e.target === overlay) {
                document.body.removeChild(overlay);
                window.removeEventListener('mousemove', moveHandler);
                window.removeEventListener('mouseup', stopHandler);
            }
        };
    };
}
