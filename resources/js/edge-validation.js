/**
 * Edge Validation Module
 * 
 * Validaciones del lado del cliente para reducir peticiones al servidor.
 * Estas validaciones se ejecutan inmediatamente en el navegador antes de
 * que Livewire envíe datos al servidor.
 * 
 * @version 1.0.0
 * @author EC-Antigravity Frontend Optimization
 */

window.EdgeValidation = {
    /**
     * Validación de formato DNI (8 dígitos numéricos)
     * @param {string} value - Valor del DNI
     * @returns {Object} - {valid: boolean, message: string}
     */
    validateDNI: (value) => {
        const normalized = (value || '').toString().trim();
        if (normalized.length === 0) return { valid: true, message: '' };
        if (normalized.length < 8) return { valid: false, message: `Faltan ${8 - normalized.length} dígitos` };
        if (normalized.length > 8) return { valid: false, message: 'El DNI debe tener 8 dígitos' };
        if (!/^\d{8}$/.test(normalized)) return { valid: false, message: 'El DNI solo debe contener números' };
        return { valid: true, message: '' };
    },

    /**
     * Validación de formato celular (9 dígitos, empieza con 9)
     * @param {string} value - Valor del celular
     * @returns {Object} - {valid: boolean, message: string}
     */
    validateCelular: (value) => {
        const normalized = (value || '').toString().trim();
        if (normalized.length === 0) return { valid: true, message: '' };
        if (normalized.length > 0 && normalized[0] !== '9') return { valid: false, message: 'El celular debe empezar con 9' };
        if (normalized.length < 9) return { valid: false, message: `Faltan ${9 - normalized.length} dígitos` };
        if (normalized.length > 9) return { valid: false, message: 'El celular debe tener 9 dígitos' };
        if (!/^9\d{8}$/.test(normalized)) return { valid: false, message: 'El celular solo debe contener números' };
        return { valid: true, message: '' };
    },

    /**
     * Validación de formato email
     * @param {string} value - Valor del correo
     * @returns {Object} - {valid: boolean, message: string}
     */
    validateCorreo: (value) => {
        const normalized = (value || '').toString().trim();
        if (normalized.length === 0) return { valid: true, message: '' };
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailRegex.test(normalized)) return { valid: false, message: 'Formato de correo inválido' };
        return { valid: true, message: '' };
    },

    /**
     * Validación de cuenta bancaria (14 dígitos)
     * @param {string} value - Valor de la cuenta
     * @returns {Object} - {valid: boolean, message: string}
     */
    validateCuenta: (value) => {
        const normalized = (value || '').toString().trim();
        if (normalized.length === 0) return { valid: true, message: '' };
        if (!/^\d+$/.test(normalized)) return { valid: false, message: 'La cuenta solo debe contener números' };
        if (normalized.length < 14) return { valid: false, message: `Faltan ${14 - normalized.length} dígitos` };
        if (normalized.length > 14) return { valid: false, message: 'La cuenta debe tener 14 dígitos' };
        return { valid: true, message: '' };
    },

    /**
     * Validación de monto (número positivo)
     * @param {string|number} value - Valor del monto
     * @param {number} min - Monto mínimo (default: 0)
     * @param {number} max - Monto máximo (default: Infinity)
     * @returns {Object} - {valid: boolean, message: string}
     */
    validateMonto: (value, min = 0, max = Infinity) => {
        const normalized = parseFloat((value || '').toString().replace(/,/g, ''));
        if (isNaN(normalized)) return { valid: false, message: 'Ingrese un monto válido' };
        if (normalized < min) return { valid: false, message: `El monto mínimo es S/ ${min}` };
        if (normalized > max) return { valid: false, message: `El monto máximo es S/ ${max}` };
        return { valid: true, message: '' };
    },

    /**
     * Validación de nombre/apellido (solo letras y espacios)
     * @param {string} value - Valor del nombre
     * @param {number} minLength - Longitud mínima (default: 2)
     * @returns {Object} - {valid: boolean, message: string}
     */
    validateNombre: (value, minLength = 2) => {
        const normalized = (value || '').toString().trim();
        if (normalized.length === 0) return { valid: true, message: '' };
        if (normalized.length < minLength) return { valid: false, message: `Mínimo ${minLength} caracteres` };
        if (!/^[a-zA-ZáéíóúÁÉÍÓÚñÑüÜ\s]+$/.test(normalized)) return { valid: false, message: 'Solo se permiten letras' };
        return { valid: true, message: '' };
    }
};

/**
 * Utilidad para aplicar estilos de error a inputs
 */
window.EdgeValidationUI = {
    /**
     * Aplica validación a un elemento input
     * @param {HTMLElement} el - Elemento input
     * @param {string} validatorName - Nombre del validador (ej: 'validateDNI')
     * @param {Object} options - Opciones adicionales
     */
    applyValidation: (el, validatorName, options = {}) => {
        const validator = window.EdgeValidation[validatorName];
        if (!validator) {
            console.warn(`EdgeValidation: Validator '${validatorName}' not found`);
            return;
        }

        const showError = (message) => {
            el.classList.add('border-red-500', 'focus:border-red-500', 'focus:ring-red-500');
            el.classList.remove('border-gray-300', 'focus:border-primary-500', 'focus:ring-primary-500');
            
            // Buscar o crear elemento de error
            let errorEl = el.parentElement.querySelector('.edge-validation-error');
            if (!errorEl && message) {
                errorEl = document.createElement('p');
                errorEl.className = 'edge-validation-error text-sm text-red-600 mt-1';
                el.parentElement.appendChild(errorEl);
            }
            if (errorEl) errorEl.textContent = message;
        };

        const clearError = () => {
            el.classList.remove('border-red-500', 'focus:border-red-500', 'focus:ring-red-500');
            el.classList.add('border-gray-300');
            
            const errorEl = el.parentElement.querySelector('.edge-validation-error');
            if (errorEl) errorEl.remove();
        };

        // Evento de input para validación en tiempo real
        el.addEventListener('input', (e) => {
            const result = validator(e.target.value, options.min, options.max);
            if (!result.valid) {
                showError(result.message);
            } else {
                clearError();
            }
        });

        // Evento blur para validación final
        el.addEventListener('blur', (e) => {
            const result = validator(e.target.value, options.min, options.max);
            if (!result.valid) {
                showError(result.message);
            }
        });
    },

    /**
     * Inicializa validaciones automáticamente basado en data-attributes
     * Uso: <input data-edge-validate="validateDNI" />
     */
    autoInit: () => {
        document.querySelectorAll('[data-edge-validate]').forEach(el => {
            const validatorName = el.dataset.edgeValidate;
            const options = {
                min: parseFloat(el.dataset.min) || undefined,
                max: parseFloat(el.dataset.max) || undefined,
            };
            window.EdgeValidationUI.applyValidation(el, validatorName, options);
        });
    }
};

// Integración con Alpine.js (usado por Filament/Livewire)
document.addEventListener('alpine:init', () => {
    if (typeof Alpine !== 'undefined') {
        // Directiva personalizada: x-edge-validate="validateDNI"
        Alpine.directive('edge-validate', (el, { expression }) => {
            const validatorName = expression;
            window.EdgeValidationUI.applyValidation(el, validatorName);
        });
    }
});

// Auto-inicializar cuando el DOM esté listo
document.addEventListener('DOMContentLoaded', () => {
    window.EdgeValidationUI.autoInit();
});

// Re-inicializar después de actualizaciones de Livewire
document.addEventListener('livewire:navigated', () => {
    window.EdgeValidationUI.autoInit();
});

// Para cargas dinámicas de Livewire (Filament modals, etc.)
if (typeof Livewire !== 'undefined') {
    Livewire.hook('morph.updated', () => {
        setTimeout(() => window.EdgeValidationUI.autoInit(), 100);
    });
}

export default window.EdgeValidation;
