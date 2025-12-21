// Globalna konfiguracija
const API_CONFIG = {
  baseUrl: "/api",
  timeout: 10000, // 10 sekund
};

/**
 * Prikaže opozorilo/obvestilo uporabniku
 * @param {string} message - Sporočilo
 * @param {string} type - Tip: 'success', 'error', 'warning', 'info'
 */
function showAlert(message, type = "info") {
  // Odstrani prejšnja opozorila
  const existingAlert = document.querySelector(".ajax-alert");
  if (existingAlert) {
    existingAlert.remove();
  }

  // Določi ikono glede na tip
  let icon = "";
  switch (type) {
    case "success":
      icon = '<i class="bi bi-check-circle-fill me-2"></i>';
      break;
    case "error":
      icon = '<i class="bi bi-x-circle-fill me-2"></i>';
      break;
    case "warning":
      icon = '<i class="bi bi-exclamation-triangle-fill me-2"></i>';
      break;
    case "info":
      icon = '<i class="bi bi-info-circle-fill me-2"></i>';
      break;
  }

  // Ustvari novo obvestilo
  const alert = document.createElement("div");
  alert.className = `alert alert-${type} alert-dismissible fade show ajax-alert`;
  alert.style.position = "fixed";
  alert.style.top = "20px";
  alert.style.right = "20px";
  alert.style.zIndex = "9999";
  alert.style.minWidth = "320px";
  alert.style.maxWidth = "500px";
  alert.style.boxShadow = "0 8px 16px rgba(0, 0, 0, 0.15)";
  alert.style.borderRadius = "8px";
  alert.style.fontWeight = "500";
  alert.style.animation = "slideInRight 0.3s ease-out";

  alert.innerHTML = `
        <div class="d-flex align-items-center">
            ${icon}
            <span>${message}</span>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    `;

  document.body.appendChild(alert);

  // Samodejno odstrani po 2 sekundah
  setTimeout(() => {
    if (alert && alert.parentNode) {
      alert.classList.remove("show");
      setTimeout(() => alert.remove(), 150);
    }
  }, 2000);
}

/**
 * Pošlje POST zahtevek na API
 * @param {string} endpoint - Pot do endpointa (npr. '/naloge.php')
 * @param {FormData|Object} data - Podatki za pošiljanje
 * @returns {Promise<Object>} - Odgovor iz API-ja
 */
async function apiPost(endpoint, data) {
  try {
    const url = API_CONFIG.baseUrl + endpoint;

    // Če so podatki objekt, pretvori v FormData
    let body = data;
    if (!(data instanceof FormData)) {
      const formData = new FormData();
      for (const key in data) {
        formData.append(key, data[key]);
      }
      body = formData;
    }

    const controller = new AbortController();
    const timeoutId = setTimeout(() => controller.abort(), API_CONFIG.timeout);

    const response = await fetch(url, {
      method: "POST",
      body: body,
      signal: controller.signal,
      credentials: "same-origin",
    });

    clearTimeout(timeoutId);

    if (!response.ok) {
      const error = await response
        .json()
        .catch(() => ({ message: "Napaka pri komunikaciji s strežnikom." }));
      throw new Error(error.message || `HTTP napaka: ${response.status}`);
    }

    return await response.json();
  } catch (error) {
    if (error.name === "AbortError") {
      throw new Error("Zahtevek je trajal predolgo.");
    }
    throw error;
  }
}

/**
 * Pošlje DELETE zahtevek na API
 * @param {string} endpoint - Pot do endpointa (npr. '/naloge.php')
 * @param {Object} data - Podatki za pošiljanje
 * @returns {Promise<Object>} - Odgovor iz API-ja
 */
async function apiDelete(endpoint, data) {
  try {
    const url = API_CONFIG.baseUrl + endpoint;

    // Pretvori objekt v URL encoded string za DELETE
    const params = new URLSearchParams(data).toString();

    const controller = new AbortController();
    const timeoutId = setTimeout(() => controller.abort(), API_CONFIG.timeout);

    const response = await fetch(url, {
      method: "DELETE",
      headers: {
        "Content-Type": "application/x-www-form-urlencoded",
      },
      body: params,
      signal: controller.signal,
      credentials: "same-origin",
    });

    clearTimeout(timeoutId);

    if (!response.ok) {
      const error = await response
        .json()
        .catch(() => ({ message: "Napaka pri komunikaciji s strežnikom." }));
      throw new Error(error.message || `HTTP napaka: ${response.status}`);
    }

    return await response.json();
  } catch (error) {
    if (error.name === "AbortError") {
      throw new Error("Zahtevek je trajal predolgo.");
    }
    throw error;
  }
}

/**
 * Prikaže lepi confirm dialog
 * @param {string} message - Sporočilo
 * @param {string} title - Naslov dialoga (opcijsko)
 * @returns {Promise<boolean>} - True če uporabnik potrdi, False če prekliče
 */
function showConfirm(message, title = "Potrditev") {
  return new Promise((resolve) => {
    // Odstrani obstoječe confirm dialoge
    const existingModal = document.getElementById("customConfirmModal");
    if (existingModal) {
      existingModal.remove();
    }

    // Ustvari modal
    const modal = document.createElement("div");
    modal.id = "customConfirmModal";
    modal.className = "modal fade";
    modal.tabIndex = -1;
    modal.innerHTML = `
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header bg-warning text-dark">
                        <h5 class="modal-title">
                            <i class="bi bi-exclamation-triangle-fill me-2"></i>${title}
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p class="mb-0">${message}</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" id="confirmCancel">
                            <i class="bi bi-x-circle me-1"></i>Prekliči
                        </button>
                        <button type="button" class="btn btn-danger" id="confirmOk">
                            <i class="bi bi-check-circle me-1"></i>Potrdi
                        </button>
                    </div>
                </div>
            </div>
        `;

    document.body.appendChild(modal);

    const bsModal = new bootstrap.Modal(modal);
    bsModal.show();

    // Event handlers
    document.getElementById("confirmOk").addEventListener("click", () => {
      bsModal.hide();
      resolve(true);
    });

    document.getElementById("confirmCancel").addEventListener("click", () => {
      bsModal.hide();
      resolve(false);
    });

    // Ko se modal zapre, odstrani iz DOMa
    modal.addEventListener("hidden.bs.modal", () => {
      modal.remove();
    });
  });
}

/**
 * Formatiraj datum v slovenski format
 * @param {string} dateString - Datum v ISO formatu
 * @returns {string} - Formatiran datum
 */
function formatDate(dateString) {
  if (!dateString) return "";
  const date = new Date(dateString);
  const options = {
    year: "numeric",
    month: "2-digit",
    day: "2-digit",
    hour: "2-digit",
    minute: "2-digit",
  };
  return date.toLocaleDateString("sl-SI", options);
}

/**
 * Formatiraj datum v slovenski format brez ure
 * @param {string} dateString - Datum v ISO formatu
 * @returns {string} - Formatiran datum
 */
function formatDateOnly(dateString) {
  if (!dateString) return "";
  const date = new Date(dateString);
  const options = { year: "numeric", month: "2-digit", day: "2-digit" };
  return date.toLocaleDateString("sl-SI", options);
}

/**
 * Preveri, ali je datum v preteklosti
 * @param {string} dateString - Datum v ISO formatu
 * @returns {boolean}
 */
function isOverdue(dateString) {
  if (!dateString) return false;
  const date = new Date(dateString);
  const today = new Date();
  today.setHours(0, 0, 0, 0);
  return date < today;
}
