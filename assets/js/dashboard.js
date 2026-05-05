let charts = {};
// On récupère le tri stocké ou on utilise le défaut (VIRTUEL desc)
let currentSort = JSON.parse(localStorage.getItem('ttcav_sort')) || { column: 6, direction: 'desc' };

document.addEventListener('DOMContentLoaded', function() {
    initTableEvents();
    initSearch();
    initSyncAll();
    initAvatarUpload();
    initAvatarSizeSelector();
    initThemeToggle();
    initAvatarHoverPreview();
    restoreState(); 
    initMobileSort(); // Initialiser le tri mobile
    
    // Détecter le clic sur la ligne (sauf si on clique sur une icône d'action)
    const table = document.querySelector('.players-table');
    if (table) {
        table.addEventListener('click', function(e) {
            const row = e.target.closest('.player-row');
            const isIcon = e.target.closest('.refresh-icon') || e.target.closest('.player-avatar') || e.target.closest('.btn-mobile-details');
            
            if (row && !isIcon) {
                toggleDetails(row.dataset.licence);
            }
        });
    }
});

function syncPlayer(licence) {
    const icon = document.querySelector(`tr[data-licence="${licence}"] .refresh-icon`);
    if (icon) icon.classList.add('fa-spin');
    
    fetch('ajax.php?action=syncPlayer&licence=' + licence)
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                location.reload(); // Recharger pour voir les nouveaux points
            } else {
                alert('Erreur : ' + data.error);
                if (icon) icon.classList.remove('fa-spin');
            }
        })
        .catch(err => {
            console.error(err);
            if (icon) icon.classList.remove('fa-spin');
        });
}

function copyToClipboard(text, element) {
    navigator.clipboard.writeText(text).then(() => {
        const originalHtml = element.innerHTML;
        element.innerHTML = '<i class="fas fa-check text-success me-1"></i> Copié !';
        element.classList.add('copied');
        
        setTimeout(() => {
            element.innerHTML = originalHtml;
            element.classList.remove('copied');
        }, 1500);
    }).catch(err => {
        console.error('Erreur lors de la copie :', err);
    });
}

function saveState() {
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
        // Appliquer au moins le tri par défaut (VIRTUEL desc)
        sortTable(6, 'desc');
        return;
    }

    // 1. Restaurer le tri
    currentSort = saved.sort;
    sortTable(currentSort.column, currentSort.direction);

    // 2. Restaurer la recherche
    const searchInput = document.getElementById('playerSearch');
    if (searchInput && saved.search) {
        searchInput.value = saved.search;
        searchInput.dispatchEvent(new Event('input'));
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
    const clearBtn = document.getElementById('searchClear');
    const showInactiveCheckbox = document.getElementById('showInactive');
    
    if (!searchInput) return;

    function applyFilters() {
        const term = searchInput.value.toLowerCase();
        const showInactive = showInactiveCheckbox ? showInactiveCheckbox.checked : true;
        
        if (clearBtn) {
            clearBtn.classList.toggle('hidden', !term);
        }
        
        const playerRows = document.querySelectorAll('.player-row');
        const playerCountSpan = document.getElementById('playerCount');
        const playerCountMobileSpan = document.getElementById('playerCountMobile');
        let visibleCount = 0;

        playerRows.forEach(row => {
            const name = row.querySelector('.player-name').textContent.toLowerCase();
            const prenom = row.querySelector('.player-prenom').textContent.toLowerCase();
            const licence = row.querySelector('.player-licence').textContent.toLowerCase();
            const progAnnee = parseFloat(row.dataset.progAnnee || 0);
            const detailsRow = document.getElementById('details-' + row.dataset.licence);

            const matchesSearch = name.includes(term) || prenom.includes(term) || licence.includes(term);
            const matchesActivity = showInactive || progAnnee !== 0;

            if (matchesSearch && matchesActivity) {
                row.classList.remove('hidden');
                visibleCount++;
                
                // Mettre à jour le rang (Desktop)
                if (row.cells[0]) {
                    row.cells[0].innerHTML = `<span class="rank-number">${visibleCount}</span>`;
                }
                
                // Mettre à jour le rang (Mobile)
                const mobileRank = row.querySelector('.player-info .rank-number.d-md-none');
                if (mobileRank) {
                    mobileRank.textContent = visibleCount;
                }
            } else {
                row.classList.add('hidden');
                if (detailsRow) detailsRow.classList.add('hidden-row');
            }
        });

        if (playerCountSpan) playerCountSpan.textContent = visibleCount;
        if (playerCountMobileSpan) playerCountMobileSpan.textContent = visibleCount;
        saveState(); // Sauvegarder après filtrage
    }

    searchInput.addEventListener('input', applyFilters);
    
    if (showInactiveCheckbox) {
        showInactiveCheckbox.addEventListener('change', applyFilters);
    }
    
    if (clearBtn) {
        clearBtn.addEventListener('click', function() {
            searchInput.value = '';
            applyFilters();
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
            row.cells[0].innerHTML = `<span class="rank-number">${displayRank}</span>`;
        }
    });

    // Sync Mobile UI if exists
    const mobileSortCol = document.getElementById('mobileSortCol');
    const mobileSortDir = document.getElementById('mobileSortDir');
    if (mobileSortCol) mobileSortCol.value = columnIndex;
    if (mobileSortDir) {
        mobileSortDir.dataset.dir = direction;
        const mobileIcon = mobileSortDir.querySelector('i');
        if (mobileIcon) {
            mobileIcon.className = direction === 'asc' ? 'fas fa-sort-amount-up' : 'fas fa-sort-amount-down';
        }
    }

    saveState(); // Sauvegarder après tri
}

function initMobileSort() {
    const mobileSortCol = document.getElementById('mobileSortCol');
    const mobileSortDir = document.getElementById('mobileSortDir');
    
    if (mobileSortCol && mobileSortDir) {
        mobileSortCol.addEventListener('change', function() {
            const colIndex = parseInt(this.value);
            const direction = mobileSortDir.dataset.dir;
            sortTable(colIndex, direction);
        });
        
        mobileSortDir.addEventListener('click', function() {
            const colIndex = parseInt(mobileSortCol.value);
            const newDir = this.dataset.dir === 'desc' ? 'asc' : 'desc';
            this.dataset.dir = newDir;
            
            // L'icône sera mise à jour par sortTable -> updateSortIcons (via sync)
            sortTable(colIndex, newDir);
        });
    }
}

function initSyncAll() {
    const btnSyncAll = document.getElementById('btnSyncAll') || document.getElementById('btnSyncAllPC');
    if (!btnSyncAll) return;

    btnSyncAll.addEventListener('click', async function() {
        if (!confirm('Synchroniser tout le club ?')) return;
        
        const rows = Array.from(document.querySelectorAll('.player-row'));
        const container = document.getElementById('syncProgressContainer');
        const progressBar = document.getElementById('syncProgressBar');
        const progressText = document.getElementById('syncProgressText');
        
        container.classList.remove('hidden');
        btnSyncAll.disabled = true;
        
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
    });
}

function toggleDetails(licence, forceOpen = false) {
    const detailRow = document.getElementById('details-' + licence);
    if (!detailRow) return;
    
    const isHidden = detailRow.classList.contains('hidden-row');
    
    if (forceOpen || isHidden) {
        detailRow.classList.remove('hidden-row');
        loadChart(licence);
        loadMatches(licence);
    } else {
        detailRow.classList.add('hidden-row');
    }
    saveState(); // Sauvegarder après ouverture/fermeture
}

function loadChart(licence) {
    const canvas = document.getElementById('chart-' + licence);
    if (!canvas || charts[licence]) return;
    const ctx = canvas.getContext('2d');

    fetch(`ajax.php?action=getHistory&licence=${licence}`)
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

    fetch(`ajax.php?action=getMatches&licence=${licence}`)
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
            // Pré-calcul des totaux par groupe
            const groupStats = {};
            let liveCount = 0;
            let validatedCount = 0;
            let hasProvisionalGlobal = false;

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

                if (m.is_validated == 0) {
                    liveCount++;
                    const matchDate = new Date(m.date_match);
                    if (matchDate.getDate() <= 10) hasProvisionalGlobal = true;
                } else {
                    validatedCount++;
                }
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
                        if (hasProvisionalGlobal) {
                            html += `
                            <div style="background: rgba(236, 201, 75, 0.1); border: 1px solid rgba(236, 201, 75, 0.3); color: #ecc94b; padding: 12px 15px; border-radius: 8px; margin-bottom: 15px; font-size: 0.8rem; line-height: 1.4;">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                <b>Note sur les points :</b> Les matchs marqués avec le badge <span class="badge" style="background: #ed8936; font-size: 0.65rem; color: white;">PROVISOIRE</span> seront recalculés correctement le 11 du mois. Les points virtuels ne prennent pas encore en compte les matchs effectués avant le 10 du mois tant que le classement officiel n'est pas paru.
                            </div>`;
                        }
                        html += `<div class="validation-separator live">Matchs non validés (${liveCount})</div>`;
                    } else {
                        html += `<div class="validation-separator validated">Matchs validés (${validatedCount})</div>`;
                    }
                    currentGroup = ''; // Forcer l'affichage de la date après le séparateur
                }
                
                if (groupKey !== currentGroup) {
                    currentGroup = groupKey;
                    const stats = groupStats[groupKey];
                    const wonStr = `<span style="color: var(--plus-color); font-weight: bold;">+${stats.won.toFixed(1)}</span>`;
                    const lostStr = `<span style="color: var(--minus-color); font-weight: bold;">${stats.lost.toFixed(1)}</span>`;
                    const totalVal = stats.total;
                    const totalColor = totalVal > 0 ? 'var(--plus-color)' : (totalVal < 0 ? 'var(--minus-color)' : '#9E9E9E');
                    const totalSign = totalVal > 0 ? '+' : '';
                    const totalStr = `<span style="color: ${totalColor}; font-weight: bold;">${totalSign}${totalVal.toFixed(1)}</span>`;
                    
                    html += `<div class="match-date-group d-flex align-items-center justify-content-between gap-2">
                                <div style="flex-grow: 1; min-width: 0; line-height: 1.2;">
                                    <i class="far fa-calendar-alt me-1"></i> ${groupKey}
                                    <span class="badge-coef">COEFF x${coef}</span>
                                </div>
                                <div class="match-group-bilan">
                                    <div style="white-space: nowrap;">${wonStr} | ${lostStr}</div>
                                    <div style="color: var(--accent-yellow); font-weight: 800; font-size: 0.65rem; text-transform: uppercase; margin-top: 1px; white-space: nowrap;">
                                        Bilan : ${totalStr}
                                    </div>
                                </div>
                             </div>`;
                }

                const isWin = m.victoire_defaite === 'V';
                const statusClass = isWin ? 'win' : 'loss';
                const pts = (m.points_resultat !== 0 && m.points_resultat !== null) ? m.points_resultat : m.points_calcules;
                const ptsLabel = (pts > 0 ? '+' : '') + Number(pts).toFixed(1);
                
                let liveBadge = '';
                if (isLive) {
                    const matchDate = new Date(m.date_match);
                    const matchDay = matchDate.getDate();
                    const isMatchProvisional = (matchDay >= 1 && matchDay <= 10);
                    
                    if (isMatchProvisional) {
                        liveBadge = `<span class="badge-virtual" style="background: #ed8936; margin-right: 5px;">PROVISOIRE</span>`;
                    }
                    liveBadge += `<span class="badge-virtual">VIRTUEL</span>`;
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

                const scoreMeeting = (m.score_ja !== null && m.score_jb !== null && m.score_ja !== undefined) ? `${m.score_ja}-${m.score_jb}` : '';
                const invalidScores = ['1-0', '0-1', '1-2', '2-1', '0-2', '2-0'];
                const displayScore = (scoreMeeting && !invalidScores.includes(scoreMeeting)) ? scoreMeeting : '';
                
                if (window.innerWidth <= 768) {
                    html += `
                    <div class="match-card ${statusClass} py-2 px-2 mb-2 d-flex align-items-center">
                        <div class="match-points-badge ${statusClass}" style="min-width: 50px; text-align: center; font-size: 0.9rem; font-weight: 800;">${ptsLabel}</div>
                        <div class="match-info flex-grow-1 ms-2">
                            <div class="d-flex flex-column gap-1">
                                <!-- Ligne 1: Points Adv, Badges, Nom Prénom -->
                                <div class="d-flex align-items-center flex-wrap gap-1" style="line-height: 1.2;">
                                    <span class="text-warning fw-bold" style="font-size: 0.8rem;">${Math.round(m.adversaire_points)} pts</span>
                                    ${liveBadge}
                                    <span class="match-opponent-name" style="font-size: 0.85rem; font-weight: 600;">${m.adversaire_nom}</span>
                                </div>
                                <!-- Ligne 2: Score rencontre, Sets, Badge V/D -->
                                <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="text-muted" style="font-size: 0.75rem;">
                                            ${displayScore ? '<i class="fas fa-poll-h me-1" style="font-size: 0.65rem;"></i>' + displayScore : ''}
                                        </div>
                                        <div style="transform: scale(0.9); transform-origin: left center;">
                                            ${setsHtml}
                                        </div>
                                    </div>
                                    <span class="badge ${isWin ? 'bg-success' : 'bg-danger'}" style="font-size: 0.65rem; padding: 2px 5px; font-weight: 800; border-radius: 4px;">
                                        ${isWin ? 'VICTOIRE' : 'DÉFAITE'}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>`;
                } else {
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
                        </div>
                    </div>`;
                }
            });
            container.innerHTML = html + `
                <div class="text-center mt-4 mb-2">
                    <button class="btn-close-details-bottom" onclick="toggleDetails('${licence}')">
                        <i class="fas fa-chevron-up me-2"></i> FERMER L'HISTORIQUE
                    </button>
                </div>
            </div>`;
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
    const icon = event.target.closest('.refresh-icon');
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

let avatarTapTimer = null;
let lastAvatarTapTime = 0;
let lastAvatarTapLicence = null;

function triggerAvatarUpload(licence) {
    if (window.innerWidth > 768) {
        // Desktop : clic simple pour upload
        currentUploadLicence = licence;
        document.getElementById('avatarInput').click();
        return;
    }

    // Mobile : Détection Double Tap
    const now = Date.now();
    const delta = now - lastAvatarTapTime;
    
    if (licence === lastAvatarTapLicence && delta < 300) {
        // DOUBLE TAP -> Upload
        clearTimeout(avatarTapTimer);
        lastAvatarTapTime = 0;
        lastAvatarTapLicence = null;
        
        currentUploadLicence = licence;
        document.getElementById('avatarInput').click();
    } else {
        // PREMIER TAP -> Attente d'un second ou preview
        lastAvatarTapTime = now;
        lastAvatarTapLicence = licence;
        
        avatarTapTimer = setTimeout(() => {
            // SINGLE TAP -> Preview Image
            const playerRow = document.querySelector(`.player-row[data-licence="${licence}"]`);
            const img = playerRow ? playerRow.querySelector('.avatar-img') : null;
            if (img) {
                showImagePreview(img.src);
            }
            lastAvatarTapTime = 0;
            lastAvatarTapLicence = null;
        }, 300);
    }
}

function showImagePreview(src) {
    let overlay = document.getElementById('imageOverlay');
    if (!overlay) {
        overlay = document.createElement('div');
        overlay.id = 'imageOverlay';
        overlay.innerHTML = `
            <div class="overlay-content">
                <img id="overlayImg" src="">
                <button class="overlay-close"><i class="fas fa-times"></i></button>
            </div>
        `;
        document.body.appendChild(overlay);
        overlay.addEventListener('click', (e) => {
            if (e.target.id === 'imageOverlay' || e.target.closest('.overlay-close')) {
                overlay.classList.remove('active');
            }
        });
    }
    
    const overlayImg = document.getElementById('overlayImg');
    overlayImg.src = src;
    overlay.classList.add('active');
}

function initAvatarSizeSelector() {
    const container = document.getElementById('avatarSizeSelector');
    if (!container) return;

    container.addEventListener('click', (e) => {
        const btn = e.target.closest('[data-size]');
        if (!btn) return;

        const size = btn.dataset.size;
        setAvatarSize(size);
        saveState();
    });
}

function setAvatarSize(size) {
    const avatars = document.querySelectorAll('.player-avatar');
    avatars.forEach(av => {
        av.classList.remove('avatar-sm', 'avatar-md', 'avatar-lg');
        av.classList.add('avatar-' + size);
    });

    // Update active button
    const container = document.getElementById('avatarSizeSelector');
    if (container) {
        container.querySelectorAll('.btn').forEach(btn => {
            btn.classList.toggle('active', btn.dataset.size === size);
        });
    }
    
    localStorage.setItem('ttcav_avatar_size', size);
}

function initAvatarHoverPreview() {
    if (window.innerWidth <= 768) return;
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
function initThemeToggle() {
    const btn = document.getElementById('themeToggleBtn');
    if (!btn) return;

    // Load saved theme
    const savedTheme = localStorage.getItem('ttcav_theme') || 'dark';
    document.body.classList.toggle('light-theme', savedTheme === 'light');
    updateThemeIcon(savedTheme);

    btn.addEventListener('click', () => {
        const isLight = document.body.classList.toggle('light-theme');
        const theme = isLight ? 'light' : 'dark';
        localStorage.setItem('ttcav_theme', theme);
        updateThemeIcon(theme);
    });
}

function updateThemeIcon(theme) {
    const btn = document.getElementById('themeToggleBtn');
    if (!btn) return;
    const icon = btn.querySelector('i');
    if (icon) {
        icon.className = theme === 'light' ? 'fas fa-sun' : 'fas fa-moon';
    }
}
