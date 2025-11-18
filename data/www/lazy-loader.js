/**
 * Lazy Loading modul za optimizirano nalaganje profilnih slik
 * 
 * Uporablja Intersection Observer API za leno nalaganje slik,
 * kar zmanjša začasni čas nalaganja in porabo podatkov.
 * 
 * @version 1.0.0
 * @date 18.11.2025
 */

(function() {
    'use strict';

    /**
     * Preveri razpoložljivost Intersection Observer API
     */
    const supportsIntersectionObserver = 'IntersectionObserver' in window;

    /**
     * Placeholder slika (1x1 transparentni PNG)
     * Uporablja se kot začasna slika pred nalaganjem dejanske slike
     */
    const PLACEHOLDER_IMAGE = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=';

    /**
     * Konfiguracija Intersection Observer-ja
     */
    const observerConfig = {
        root: null, // Uporablja viewport kot root
        rootMargin: '50px', // Začne nalagati 50px pred prihodom v viewport
        threshold: 0.01 // 1% elementa mora biti vidnega
    };

    /**
     * Nastavi začetno stanje slike za lazy loading
     * @param {HTMLImageElement} img - Slika element
     */
    function setupLazyImage(img) {
        const actualSrc = img.getAttribute('src');
        
        // Če slika nima src atributa, jo preskočimo
        if (!actualSrc || actualSrc === PLACEHOLDER_IMAGE) {
            return;
        }

        // Shranimo dejanski src v data atribut
        img.setAttribute('data-src', actualSrc);
        
        // Nastavimo placeholder
        img.setAttribute('src', PLACEHOLDER_IMAGE);
        
        // Dodamo CSS razred za styling
        img.classList.add('lazy-loading');
        
        // Nastavimo alt text za dostopnost
        if (!img.hasAttribute('alt')) {
            img.setAttribute('alt', 'Profilna slika');
        }
    }

    /**
     * Naloži sliko
     * @param {HTMLImageElement} img - Slika element
     */
    function loadImage(img) {
        const src = img.getAttribute('data-src');
        
        if (!src) {
            return;
        }

        // Dodamo loading razred
        img.classList.add('lazy-loading');

        // Kreiramo novo sliko za preload
        const tempImage = new Image();
        
        tempImage.onload = function() {
            // Ko je slika naložena, zamenjamo src
            img.setAttribute('src', src);
            img.classList.remove('lazy-loading');
            img.classList.add('lazy-loaded');
            
            // Odstranimo data-src atribut
            img.removeAttribute('data-src');
        };
        
        tempImage.onerror = function() {
            // Pri napaki prikažemo placeholder ikono
            img.classList.remove('lazy-loading');
            img.classList.add('lazy-error');
            console.warn('Napaka pri nalaganju slike:', src);
        };
        
        // Začnemo z nalaganjem
        tempImage.src = src;
    }

    /**
     * Inicializacija lazy loading z Intersection Observer
     */
    function initIntersectionObserver() {
        const images = document.querySelectorAll('img[data-lazy="true"]');
        
        if (images.length === 0) {
            return;
        }

        const observer = new IntersectionObserver(function(entries, observer) {
            entries.forEach(function(entry) {
                if (entry.isIntersecting) {
                    const img = entry.target;
                    loadImage(img);
                    observer.unobserve(img); // Prenehamo opazovati po nalaganju
                }
            });
        }, observerConfig);

        // Opazujemo vse slike
        images.forEach(function(img) {
            setupLazyImage(img);
            observer.observe(img);
        });
    }

    /**
     * Fallback za brskalniki brez Intersection Observer
     */
    function initFallback() {
        const images = document.querySelectorAll('img[data-lazy="true"]');
        
        images.forEach(function(img) {
            setupLazyImage(img);
            
            // V fallback načinu naložimo vse slike takoj
            // (lahko bi tudi implementirali scroll event listener)
            loadImage(img);
        });
    }

    /**
     * Funkcija za ročno inicializacijo lazy loading-a na novih elementih
     * Uporabno za dinamično dodane elemente (AJAX)
     * @param {HTMLElement} container - Container element (opcijsko)
     */
    function refreshLazyImages(container) {
        const root = container || document;
        const images = root.querySelectorAll('img[data-lazy="true"]:not(.lazy-loaded):not(.lazy-loading)');
        
        if (images.length === 0) {
            return;
        }

        if (supportsIntersectionObserver) {
            const observer = new IntersectionObserver(function(entries, observer) {
                entries.forEach(function(entry) {
                    if (entry.isIntersecting) {
                        const img = entry.target;
                        loadImage(img);
                        observer.unobserve(img);
                    }
                });
            }, observerConfig);

            images.forEach(function(img) {
                setupLazyImage(img);
                observer.observe(img);
            });
        } else {
            images.forEach(function(img) {
                setupLazyImage(img);
                loadImage(img);
            });
        }
    }

    /**
     * Glavna inicializacijska funkcija
     */
    function init() {
        // Počakamo, da se DOM v celoti naloži
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', function() {
                if (supportsIntersectionObserver) {
                    initIntersectionObserver();
                } else {
                    console.warn('Intersection Observer ni podprt. Uporablja se fallback metoda.');
                    initFallback();
                }
            });
        } else {
            // DOM je že naložen
            if (supportsIntersectionObserver) {
                initIntersectionObserver();
            } else {
                initFallback();
            }
        }
    }

    // Izvozi funkcijo za refresh (za AJAX)
    window.lazyLoader = {
        refresh: refreshLazyImages
    };

    // Avtomatska inicializacija
    init();

})();
