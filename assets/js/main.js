/* ========================================
   EVENT MANAGER - JAVASCRIPT MODERNE
   Interactions et animations avancées
   ======================================== */

// Utilitaires généraux
const EventManager = {
  // Initialisation
  init() {
    this.setupAnimations();
    this.setupInteractions();
    this.setupNotifications();
    this.setupModals();
    this.setupForms();
    this.setupTooltips();
  },

  // Animations d'entrée
  setupAnimations() {
    const observerOptions = {
      threshold: 0.1,
      rootMargin: '0px 0px -50px 0px'
    };

    const observer = new IntersectionObserver((entries) => {
      entries.forEach(entry => {
        if (entry.isIntersecting) {
          entry.target.classList.add('animate-fadeIn');
          observer.unobserve(entry.target);
        }
      });
    }, observerOptions);

    // Observer les éléments à animer
    document.querySelectorAll('.stat-card-modern, .event-card-modern, .club-card-modern, .action-card-modern').forEach(el => {
      observer.observe(el);
    });
  },

  // Interactions avancées
  setupInteractions() {
    // Effet de survol sur les cartes
    document.querySelectorAll('.stat-card-modern, .event-card-modern, .club-card-modern').forEach(card => {
      card.addEventListener('mouseenter', function() {
        this.style.transform = 'translateY(-8px) scale(1.02)';
      });
      
      card.addEventListener('mouseleave', function() {
        this.style.transform = 'translateY(0) scale(1)';
      });
    });

    // Effet de clic sur les boutons
    document.querySelectorAll('.btn').forEach(btn => {
      btn.addEventListener('click', function(e) {
        // Créer un effet de ripple
        const ripple = document.createElement('span');
        const rect = this.getBoundingClientRect();
        const size = Math.max(rect.width, rect.height);
        const x = e.clientX - rect.left - size / 2;
        const y = e.clientY - rect.top - size / 2;
        
        ripple.style.cssText = `
          position: absolute;
          width: ${size}px;
          height: ${size}px;
          left: ${x}px;
          top: ${y}px;
          background: rgba(255, 255, 255, 0.3);
          border-radius: 50%;
          transform: scale(0);
          animation: ripple 0.6s ease-out;
          pointer-events: none;
        `;
        
        this.style.position = 'relative';
        this.style.overflow = 'hidden';
        this.appendChild(ripple);
        
        setTimeout(() => ripple.remove(), 600);
      });
    });
  },

  // Système de notifications
  setupNotifications() {
    this.notificationContainer = document.createElement('div');
    this.notificationContainer.className = 'notification-container';
    this.notificationContainer.style.cssText = `
      position: fixed;
      top: 20px;
      right: 20px;
      z-index: 10000;
      display: flex;
      flex-direction: column;
      gap: 10px;
    `;
    document.body.appendChild(this.notificationContainer);
  },

  showNotification(message, type = 'info', duration = 5000) {
    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    notification.style.cssText = `
      background: white;
      border-radius: 12px;
      padding: 16px 20px;
      box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
      border-left: 4px solid;
      transform: translateX(100%);
      transition: transform 0.3s ease;
      max-width: 350px;
      font-weight: 500;
    `;

    const colors = {
      success: '#10b981',
      error: '#ef4444',
      warning: '#f59e0b',
      info: '#3b82f6'
    };

    notification.style.borderLeftColor = colors[type] || colors.info;
    notification.style.color = colors[type] || colors.info;
    notification.textContent = message;

    this.notificationContainer.appendChild(notification);

    // Animation d'entrée
    setTimeout(() => {
      notification.style.transform = 'translateX(0)';
    }, 100);

    // Suppression automatique
    setTimeout(() => {
      notification.style.transform = 'translateX(100%)';
      setTimeout(() => notification.remove(), 300);
    }, duration);
  },

  // Système de modales
  setupModals() {
    document.querySelectorAll('[data-modal]').forEach(trigger => {
      trigger.addEventListener('click', (e) => {
        e.preventDefault();
        const modalId = trigger.getAttribute('data-modal');
        this.openModal(modalId);
      });
    });

    // Fermer les modales
    document.addEventListener('click', (e) => {
      if (e.target.classList.contains('modal-overlay')) {
        this.closeModal(e.target.closest('.modal'));
      }
    });

    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') {
        const openModal = document.querySelector('.modal.active');
        if (openModal) {
          this.closeModal(openModal);
        }
      }
    });
  },

  openModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
      modal.classList.add('active');
      document.body.style.overflow = 'hidden';
      
      // Animation d'entrée
      const content = modal.querySelector('.modal-content');
      content.style.transform = 'scale(0.9)';
      content.style.opacity = '0';
      
      setTimeout(() => {
        content.style.transform = 'scale(1)';
        content.style.opacity = '1';
      }, 100);
    }
  },

  closeModal(modal) {
    if (modal) {
      const content = modal.querySelector('.modal-content');
      content.style.transform = 'scale(0.9)';
      content.style.opacity = '0';
      
      setTimeout(() => {
        modal.classList.remove('active');
        document.body.style.overflow = '';
      }, 200);
    }
  },

  // Amélioration des formulaires
  setupForms() {
    // Validation en temps réel
    document.querySelectorAll('.form-input-modern').forEach(input => {
      input.addEventListener('blur', () => {
        this.validateField(input);
      });

      input.addEventListener('input', () => {
        this.clearFieldError(input);
      });
    });

    // Soumission des formulaires
    document.querySelectorAll('form').forEach(form => {
      form.addEventListener('submit', (e) => {
        if (!this.validateForm(form)) {
          e.preventDefault();
        }
      });
    });
  },

  validateField(field) {
    const value = field.value.trim();
    const type = field.type;
    const required = field.hasAttribute('required');
    
    let isValid = true;
    let message = '';

    if (required && !value) {
      isValid = false;
      message = 'Ce champ est obligatoire';
    } else if (type === 'email' && value && !this.isValidEmail(value)) {
      isValid = false;
      message = 'Adresse email invalide';
    } else if (type === 'password' && value && value.length < 6) {
      isValid = false;
      message = 'Le mot de passe doit contenir au moins 6 caractères';
    }

    this.setFieldError(field, isValid ? '' : message);
    return isValid;
  },

  validateForm(form) {
    let isValid = true;
    const fields = form.querySelectorAll('.form-input-modern[required]');
    
    fields.forEach(field => {
      if (!this.validateField(field)) {
        isValid = false;
      }
    });

    return isValid;
  },

  setFieldError(field, message) {
    const group = field.closest('.form-group-modern');
    const existingError = group.querySelector('.field-error');
    
    if (existingError) {
      existingError.remove();
    }

    if (message) {
      const error = document.createElement('div');
      error.className = 'field-error';
      error.style.cssText = `
        color: #ef4444;
        font-size: 0.75rem;
        margin-top: 0.25rem;
        font-weight: 500;
      `;
      error.textContent = message;
      group.appendChild(error);
      
      field.style.borderColor = '#ef4444';
    } else {
      field.style.borderColor = '';
    }
  },

  clearFieldError(field) {
    const group = field.closest('.form-group-modern');
    const error = group.querySelector('.field-error');
    if (error) {
      error.remove();
      field.style.borderColor = '';
    }
  },

  isValidEmail(email) {
    const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return re.test(email);
  },

  // Tooltips
  setupTooltips() {
    document.querySelectorAll('[data-tooltip]').forEach(element => {
      element.addEventListener('mouseenter', (e) => {
        this.showTooltip(e.target, e.target.getAttribute('data-tooltip'));
      });

      element.addEventListener('mouseleave', () => {
        this.hideTooltip();
      });
    });
  },

  showTooltip(element, text) {
    const tooltip = document.createElement('div');
    tooltip.className = 'tooltip';
    tooltip.textContent = text;
    tooltip.style.cssText = `
      position: absolute;
      background: #1f2937;
      color: white;
      padding: 8px 12px;
      border-radius: 6px;
      font-size: 0.75rem;
      font-weight: 500;
      z-index: 1000;
      pointer-events: none;
      opacity: 0;
      transform: translateY(5px);
      transition: all 0.2s ease;
    `;

    document.body.appendChild(tooltip);

    const rect = element.getBoundingClientRect();
    tooltip.style.left = rect.left + rect.width / 2 - tooltip.offsetWidth / 2 + 'px';
    tooltip.style.top = rect.top - tooltip.offsetHeight - 8 + 'px';

    setTimeout(() => {
      tooltip.style.opacity = '1';
      tooltip.style.transform = 'translateY(0)';
    }, 10);

    this.currentTooltip = tooltip;
  },

  hideTooltip() {
    if (this.currentTooltip) {
      this.currentTooltip.style.opacity = '0';
      this.currentTooltip.style.transform = 'translateY(5px)';
      setTimeout(() => {
        this.currentTooltip.remove();
        this.currentTooltip = null;
      }, 200);
    }
  },

  // Utilitaires
  formatDate(date) {
    return new Intl.DateTimeFormat('fr-FR', {
      year: 'numeric',
      month: 'long',
      day: 'numeric'
    }).format(new Date(date));
  },

  formatPrice(price) {
    return new Intl.NumberFormat('fr-FR', {
      style: 'currency',
      currency: 'EUR'
    }).format(price);
  },

  debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
      const later = () => {
        clearTimeout(timeout);
        func(...args);
      };
      clearTimeout(timeout);
      timeout = setTimeout(later, wait);
    };
  },

  throttle(func, limit) {
    let inThrottle;
    return function() {
      const args = arguments;
      const context = this;
      if (!inThrottle) {
        func.apply(context, args);
        inThrottle = true;
        setTimeout(() => inThrottle = false, limit);
      }
    };
  }
};

// Styles CSS pour les animations
const style = document.createElement('style');
style.textContent = `
  @keyframes ripple {
    to {
      transform: scale(4);
      opacity: 0;
    }
  }

  .modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 1000;
    opacity: 0;
    visibility: hidden;
    transition: all 0.3s ease;
  }

  .modal.active {
    opacity: 1;
    visibility: visible;
  }

  .modal-content {
    background: white;
    border-radius: 16px;
    padding: 2rem;
    max-width: 500px;
    width: 90%;
    max-height: 90vh;
    overflow-y: auto;
    transform: scale(0.9);
    transition: all 0.3s ease;
  }

  .modal.active .modal-content {
    transform: scale(1);
  }

  .notification {
    animation: slideInRight 0.3s ease;
  }

  @keyframes slideInRight {
    from {
      transform: translateX(100%);
    }
    to {
      transform: translateX(0);
    }
  }
`;
document.head.appendChild(style);

// Initialisation au chargement de la page
document.addEventListener('DOMContentLoaded', () => {
  EventManager.init();
});

// Export pour utilisation globale
window.EventManager = EventManager;
