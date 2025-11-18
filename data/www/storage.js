// Preveri, ali je storage na voljo (lahko ga onemogočijo brskalniki v zasebnem načinu)
const STORAGE_AVAILABLE = {
    local: checkStorageAvailable('localStorage'),
    session: checkStorageAvailable('sessionStorage')
};

/**
 * Preveri, ali je določen tip storage-a na voljo
 * @param {string} type - 'localStorage' ali 'sessionStorage'
 * @returns {boolean}
 */
function checkStorageAvailable(type) {
    try {
        const storage = window[type];
        const testKey = '__storage_test__';
        storage.setItem(testKey, 'test');
        storage.removeItem(testKey);
        return true;
    } catch (e) {
        console.warn(`${type} ni na voljo:`, e);
        return false;
    }
}

/**
 * Shrani vrednost v localStorage
 * @param {string} key - Ključ
 * @param {*} value - Vrednost (bo serializirana v JSON)
 * @returns {boolean} Uspešnost operacije
 */
function saveToLocal(key, value) {
    if (!STORAGE_AVAILABLE.local) {
        return false;
    }
    
    try {
        const serialized = JSON.stringify(value);
        localStorage.setItem(key, serialized);
        return true;
    } catch (e) {
        console.error('Napaka pri shranjevanju v localStorage:', e);
        return false;
    }
}

/**
 * Preberi vrednost iz localStorage
 * @param {string} key - Ključ
 * @param {*} defaultValue - Privzeta vrednost, če ključ ne obstaja
 * @returns {*} Prebrana vrednost ali privzeta vrednost
 */
function getFromLocal(key, defaultValue = null) {
    if (!STORAGE_AVAILABLE.local) {
        return defaultValue;
    }
    
    try {
        const item = localStorage.getItem(key);
        if (item === null) {
            return defaultValue;
        }
        return JSON.parse(item);
    } catch (e) {
        console.error('Napaka pri branju iz localStorage:', e);
        return defaultValue;
    }
}

/**
 * Odstrani vrednost iz localStorage
 * @param {string} key - Ključ
 * @returns {boolean} Uspešnost operacije
 */
function removeFromLocal(key) {
    if (!STORAGE_AVAILABLE.local) {
        return false;
    }
    
    try {
        localStorage.removeItem(key);
        return true;
    } catch (e) {
        console.error('Napaka pri odstranjevanju iz localStorage:', e);
        return false;
    }
}

/**
 * Počisti vse vrednosti iz localStorage
 * @returns {boolean} Uspešnost operacije
 */
function clearLocal() {
    if (!STORAGE_AVAILABLE.local) {
        return false;
    }
    
    try {
        localStorage.clear();
        return true;
    } catch (e) {
        console.error('Napaka pri čiščenju localStorage:', e);
        return false;
    }
}

/**
 * Shrani vrednost v sessionStorage
 * @param {string} key - Ključ
 * @param {*} value - Vrednost (bo serializirana v JSON)
 * @returns {boolean} Uspešnost operacije
 */
function saveToSession(key, value) {
    if (!STORAGE_AVAILABLE.session) {
        return false;
    }
    
    try {
        const serialized = JSON.stringify(value);
        sessionStorage.setItem(key, serialized);
        return true;
    } catch (e) {
        console.error('Napaka pri shranjevanju v sessionStorage:', e);
        return false;
    }
}

/**
 * Preberi vrednost iz sessionStorage
 * @param {string} key - Ključ
 * @param {*} defaultValue - Privzeta vrednost, če ključ ne obstaja
 * @returns {*} Prebrana vrednost ali privzeta vrednost
 */
function getFromSession(key, defaultValue = null) {
    if (!STORAGE_AVAILABLE.session) {
        return defaultValue;
    }
    
    try {
        const item = sessionStorage.getItem(key);
        if (item === null) {
            return defaultValue;
        }
        return JSON.parse(item);
    } catch (e) {
        console.error('Napaka pri branju iz sessionStorage:', e);
        return defaultValue;
    }
}

/**
 * Odstrani vrednost iz sessionStorage
 * @param {string} key - Ključ
 * @returns {boolean} Uspešnost operacije
 */
function removeFromSession(key) {
    if (!STORAGE_AVAILABLE.session) {
        return false;
    }
    
    try {
        sessionStorage.removeItem(key);
        return true;
    } catch (e) {
        console.error('Napaka pri odstranjevanju iz sessionStorage:', e);
        return false;
    }
}

/**
 * Počisti vse vrednosti iz sessionStorage
 * @returns {boolean} Uspešnost operacije
 */
function clearSession() {
    if (!STORAGE_AVAILABLE.session) {
        return false;
    }
    
    try {
        sessionStorage.clear();
        return true;
    } catch (e) {
        console.error('Napaka pri čiščenju sessionStorage:', e);
        return false;
    }
}

// === Specifične funkcije za uporabniške preference ===

/**
 * Shrani filter preference za index.php
 * @param {Object} filters - Objekt s filtri (search, tip, rok_od, rok_do, advancedOpen)
 */
function saveTaskFilters(filters) {
    return saveToLocal('task_filters', filters);
}

/**
 * Pridobi shranjene filter preference
 * @returns {Object|null}
 */
function getTaskFilters() {
    return getFromLocal('task_filters', null);
}

/**
 * Shrani preference sortiranja
 * @param {Object} sorting - Objekt s sort stolpcem in smerjo (column, direction)
 */
function saveSortPreferences(sorting) {
    return saveToLocal('task_sorting', sorting);
}

/**
 * Pridobi shranjene preference sortiranja
 * @returns {Object|null}
 */
function getSortPreferences() {
    return getFromLocal('task_sorting', null);
}

/**
 * Shrani ID zadnje odprte skupine (sessionStorage)
 * @param {number} groupId - ID skupine
 */
function saveLastOpenedGroup(groupId) {
    return saveToSession('last_opened_group', groupId);
}

/**
 * Pridobi ID zadnje odprte skupine
 * @returns {number|null}
 */
function getLastOpenedGroup() {
    return getFromSession('last_opened_group', null);
}

/**
 * Počisti vse uporabniške preference (localStorage)
 * Ohrani sessionStorage, ker so podatki specifični za sejo
 */
function clearUserPreferences() {
    const keysToRemove = ['task_filters', 'task_sorting'];
    let success = true;
    
    keysToRemove.forEach(key => {
        if (!removeFromLocal(key)) {
            success = false;
        }
    });
    
    return success;
}

/**
 * Shrani preference teme (svetla/temna)
 * @param {string} theme - 'light' ali 'dark'
 */
function saveThemePreference(theme) {
    return saveToLocal('theme', theme);
}

/**
 * Pridobi preference teme
 * @returns {string|null}
 */
function getThemePreference() {
    return getFromLocal('theme', 'light');
}
