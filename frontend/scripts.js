/**
 * FONCTIONS UTILITAIRES GLOBALES
 * Fichier JavaScript pour toute l'application
 */

// ==========================================
// GESTION DES MODALS
// ==========================================
function openModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
    }
}

function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.remove('active');
        document.body.style.overflow = 'auto';
    }
}

// Fermer modal en cliquant en dehors
document.addEventListener('click', function(e) {
    if (e.target.classList.contains('modal')) {
        closeModal(e.target.id);
    }
});

// ==========================================
// VALIDATION DE FORMULAIRE
// ==========================================
function validateEmail(email) {
    const regex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return regex.test(email);
}

function validatePhone(phone) {
    const regex = /^[\d\s\+\-\(\)]+$/;
    return regex.test(phone);
}

function validatePassword(password) {
    // Au moins 8 caractères, 1 majuscule, 1 minuscule, 1 chiffre
    const regex = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).{8,}$/;
    return regex.test(password);
}

// ==========================================
// VÉRIFICATION EMAIL EN TEMPS RÉEL (AJAX)
// ==========================================
async function checkEmailAvailability(email, displayElementId) {
    if (!validateEmail(email)) {
        return;
    }

    const displayElement = document.getElementById(displayElementId);
    
    try {
        const response = await fetch('../ajax/check_email.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: 'email=' + encodeURIComponent(email)
        });

        const data = await response.json();

        if (data.exists) {
            displayElement.textContent = '❌ Email déjà utilisé';
            displayElement.className = 'form-error';
            return false;
        } else {
            displayElement.textContent = '✓ Email disponible';
            displayElement.className = 'form-success';
            return true;
        }
    } catch (error) {
        console.error('Erreur:', error);
        displayElement.textContent = 'Erreur de vérification';
        displayElement.className = 'form-error';
        return false;
    }
}

// ==========================================
// CONFIRMATION DE SUPPRESSION
// ==========================================
function confirmDelete(message = 'Êtes-vous sûr de vouloir supprimer cet élément ?') {
    return confirm(message);
}

// ==========================================
// TOAST NOTIFICATIONS
// ==========================================
function showToast(message, type = 'info') {
    // Créer le toast s'il n'existe pas
    let toastContainer = document.getElementById('toast-container');
    if (!toastContainer) {
        toastContainer = document.createElement('div');
        toastContainer.id = 'toast-container';
        toastContainer.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
            display: flex;
            flex-direction: column;
            gap: 10px;
        `;
        document.body.appendChild(toastContainer);
    }

    const toast = document.createElement('div');
    toast.className = `alert alert-${type} fade-in`;
    toast.style.cssText = `
        min-width: 300px;
        box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        animation: slideInRight 0.3s;
    `;
    
    const icon = {
        success: '✓',
        danger: '❌',
        warning: '⚠️',
        info: 'ℹ️'
    }[type] || 'ℹ️';

    toast.innerHTML = `
        <strong>${icon}</strong>
        <span>${message}</span>
    `;

    toastContainer.appendChild(toast);

    // Supprimer après 4 secondes
    setTimeout(() => {
        toast.style.animation = 'slideOutRight 0.3s';
        setTimeout(() => toast.remove(), 300);
    }, 4000);
}

// Ajouter les animations CSS si elles n'existent pas
if (!document.getElementById('toast-animations')) {
    const style = document.createElement('style');
    style.id = 'toast-animations';
    style.textContent = `
        @keyframes slideInRight {
            from {
                transform: translateX(400px);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
        @keyframes slideOutRight {
            from {
                transform: translateX(0);
                opacity: 1;
            }
            to {
                transform: translateX(400px);
                opacity: 0;
            }
        }
    `;
    document.head.appendChild(style);
}

// ==========================================
// LOADING SPINNER
// ==========================================
function showLoading(containerId) {
    const container = document.getElementById(containerId);
    if (container) {
        container.innerHTML = '<div class="spinner"></div>';
    }
}

function hideLoading(containerId) {
    const container = document.getElementById(containerId);
    if (container) {
        container.innerHTML = '';
    }
}

// ==========================================
// FORMAT DATE
// ==========================================
function formatDate(dateString) {
    const options = { year: 'numeric', month: 'long', day: 'numeric' };
    const date = new Date(dateString);
    return date.toLocaleDateString('fr-FR', options);
}

function formatDateTime(dateString) {
    const options = { 
        year: 'numeric', 
        month: 'long', 
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    };
    const date = new Date(dateString);
    return date.toLocaleDateString('fr-FR', options);
}

// ==========================================
// DEBOUNCE (pour optimiser les recherches)
// ==========================================
function debounce(func, delay = 300) {
    let timeoutId;
    return function(...args) {
        clearTimeout(timeoutId);
        timeoutId = setTimeout(() => func.apply(this, args), delay);
    };
}

// ==========================================
// RECHERCHE EN TEMPS RÉEL
// ==========================================
function setupLiveSearch(inputId, targetSelector, searchKey) {
    const input = document.getElementById(inputId);
    if (!input) return;

    const debouncedSearch = debounce(function() {
        const searchTerm = this.value.toLowerCase();
        const items = document.querySelectorAll(targetSelector);

        items.forEach(item => {
            const text = item.getAttribute(searchKey) || item.textContent;
            if (text.toLowerCase().includes(searchTerm)) {
                item.style.display = '';
            } else {
                item.style.display = 'none';
            }
        });
    }, 300);

    input.addEventListener('input', debouncedSearch);
}

// ==========================================
// COPIER DANS LE PRESSE-PAPIER
// ==========================================
async function copyToClipboard(text) {
    try {
        await navigator.clipboard.writeText(text);
        showToast('Copié dans le presse-papier !', 'success');
        return true;
    } catch (err) {
        console.error('Erreur de copie:', err);
        showToast('Erreur lors de la copie', 'danger');
        return false;
    }
}

// ==========================================
// CONFIRMATION AVANT DÉPART (formulaire non sauvegardé)
// ==========================================
let formModified = false;

function trackFormChanges(formId) {
    const form = document.getElementById(formId);
    if (!form) return;

    form.addEventListener('input', () => {
        formModified = true;
    });

    form.addEventListener('submit', () => {
        formModified = false;
    });

    window.addEventListener('beforeunload', (e) => {
        if (formModified) {
            e.preventDefault();
            e.returnValue = '';
        }
    });
}

// ==========================================
// AUTO-RESIZE TEXTAREA
// ==========================================
function autoResizeTextarea(textareaId) {
    const textarea = document.getElementById(textareaId);
    if (!textarea) return;

    textarea.addEventListener('input', function() {
        this.style.height = 'auto';
        this.style.height = this.scrollHeight + 'px';
    });
}

// ==========================================
// PREVIEW IMAGE AVANT UPLOAD
// ==========================================
function previewImage(inputId, previewId) {
    const input = document.getElementById(inputId);
    const preview = document.getElementById(previewId);
    
    if (!input || !preview) return;

    input.addEventListener('change', function(e) {
        const file = e.target.files[0];
        
        if (file && file.type.startsWith('image/')) {
            const reader = new FileReader();
            
            reader.onload = function(e) {
                preview.src = e.target.result;
                preview.style.display = 'block';
            };
            
            reader.readAsDataURL(file);
        }
    });
}

// ==========================================
// PAGINATION
// ==========================================
function setupPagination(itemsSelector, itemsPerPage = 10) {
    const items = document.querySelectorAll(itemsSelector);
    const totalPages = Math.ceil(items.length / itemsPerPage);
    let currentPage = 1;

    function showPage(page) {
        items.forEach((item, index) => {
            const start = (page - 1) * itemsPerPage;
            const end = start + itemsPerPage;
            item.style.display = (index >= start && index < end) ? '' : 'none';
        });
    }

    function createPaginationControls(containerId) {
        const container = document.getElementById(containerId);
        if (!container) return;

        container.innerHTML = '';
        
        for (let i = 1; i <= totalPages; i++) {
            const btn = document.createElement('button');
            btn.textContent = i;
            btn.className = 'btn btn-sm';
            if (i === currentPage) btn.classList.add('btn-primary');
            
            btn.addEventListener('click', () => {
                currentPage = i;
                showPage(currentPage);
                createPaginationControls(containerId);
            });
            
            container.appendChild(btn);
        }
    }

    showPage(currentPage);
    return { showPage, createPaginationControls, totalPages };
}

// ==========================================
// INITIALISATION AU CHARGEMENT
// ==========================================
document.addEventListener('DOMContentLoaded', function() {
    // Fermer les alerts automatiquement
    document.querySelectorAll('.alert').forEach(alert => {
        setTimeout(() => {
            alert.style.animation = 'fadeOut 0.5s';
            setTimeout(() => alert.remove(), 500);
        }, 5000);
    });

    // Ajouter l'animation fadeOut si elle n'existe pas
    if (!document.getElementById('alert-animations')) {
        const style = document.createElement('style');
        style.id = 'alert-animations';
        style.textContent = `
            @keyframes fadeOut {
                to { opacity: 0; transform: translateY(-10px); }
            }
        `;
        document.head.appendChild(style);
    }
});

// ==========================================
// EXPORT (pour utilisation avec modules)
// ==========================================
if (typeof module !== 'undefined' && module.exports) {
    module.exports = {
        validateEmail,
        validatePhone,
        validatePassword,
        checkEmailAvailability,
        showToast,
        formatDate,
        formatDateTime,
        debounce,
        copyToClipboard
    };
}