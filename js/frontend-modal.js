/**
 * Frontend Modal JavaScript - Tab-based Redesign
 * Luo slide-over modal työpaikan infopaketin näyttämiseen
 * Tuki: Tabit, Video, Kuvagalleria, Lightbox, Mobiiliresponsiivisuus
 */
(function() {
    'use strict';

    // Estä tuplarekisteröinnit: skripti ajetaan vain kerran per sivulataus,
    // vaikka se löytyisi sivulta useaan kertaan tai jokin plugin käynnistäisi sen uudelleen.
    if (window.TJobsModalInitialized) {
        return;
    }
    window.TJobsModalInitialized = true;

    let currentJobId = null;
    let currentLang = null;
    let currentApplyUrl = '';
    let modalElement = null;
    let lightboxElement = null;
    let i18n = {};
    let currentTab = 'announcement';
    let closeTimer = null;

    // Wizard state
    let wizardSteps = [];
    let wizardCurrentIndex = 0;
    let wizardVisited = new Set();
    let wizardForceLinear = true;
    let wizardApplyUrl = '';

    /**
     * Tarkista onko elementti yhä liitetty dokumenttiin (ei stale/detached viite).
     *
     * @param {Element|null} el - Tarkistettava DOM-elementti.
     * @returns {boolean} true jos elementti on liitetty dokumenttiin, muuten false.
     */
    function isInDocument(el) {
        return el && document.body && document.body.contains(el);
    }

    /**
     * Alusta modal
     */
    function init() {
        // Käytä event delegation job-linkkien klikkauksiin.
        // Capture-vaihe (true) varmistaa, että oma koodimme nappaa klikkauksen
        // ennen kuin Elementor Pro tai Ajax Search Pro ehtii estää sen stopPropagation():lla.
        document.addEventListener('click', function(e) {
            const jobLink = e.target.closest('[data-job-id]');
            if (jobLink) {
                e.preventDefault();
                e.stopPropagation();
                const jobId = jobLink.getAttribute('data-job-id');
                const applyUrl = jobLink.getAttribute('data-apply-url') || jobLink.getAttribute('href') || '';
                if (jobId) {
                    openModal(jobId, null, applyUrl);
                }
            }
        }, true);

        // ESC-näppäin sulkee modalin tai lightboxin
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                if (lightboxElement && lightboxElement.classList.contains('is-open')) {
                    closeLightbox();
                } else if (modalElement) {
                    closeModal();
                }
            }
        });

        // Käsittele bfcache-palautus: varmista modal on suljettu ja tila on puhdas.
        window.addEventListener('pageshow', function(e) {
            if (e.persisted) {
                // Nollaa aina body overflow riippumatta modal-tilasta
                document.body.style.overflow = '';

                // Peruuta mahdollinen odottava sulkemis-timeout
                if (closeTimer) {
                    clearTimeout(closeTimer);
                    closeTimer = null;
                }

                // Jos modalElement on stale viite (ei enää DOM:ssa), nollaa se
                if (modalElement && !isInDocument(modalElement)) {
                    modalElement = null;
                }

                if (modalElement) {
                    modalElement.classList.remove('is-open');
                    const content = modalElement.querySelector('.tjobs-modal__content');
                    if (content) {
                        content.innerHTML = '';
                    }
                }

                // Lightbox myös kiinni
                if (lightboxElement && !isInDocument(lightboxElement)) {
                    lightboxElement = null;
                }
            }
        });
    }

    /**
     * Avaa modal
     */
    function openModal(jobId, lang, applyUrl) {
        currentJobId = jobId;
        currentLang = lang || (window.tjobsModalConfig ? window.tjobsModalConfig.lang : 'fi');
        currentApplyUrl = applyUrl || '';
        currentTab = 'announcement'; // Reset to first tab

        // Reset wizard state
        wizardSteps = [];
        wizardCurrentIndex = 0;
        wizardVisited = new Set([0]);
        wizardApplyUrl = applyUrl || '';

        // Peruuta mahdollinen odottava sulkemis-timeout
        if (closeTimer) {
            clearTimeout(closeTimer);
            closeTimer = null;
        }

        // Tarkista onko modalElement yhä DOM:ssa (esim. bfcache tai ulkoinen manipulaatio saattaa irrottaa sen)
        if (modalElement && !isInDocument(modalElement)) {
            modalElement = null;
        }

        // Luo modal DOM jos ei ole vielä
        if (!modalElement) {
            createModalDOM();
        }

        // Näytä modal
        modalElement.classList.add('is-open');
        document.body.style.overflow = 'hidden';

        // Lataa data
        loadJobData(currentJobId, currentLang);
    }

    /**
     * Sulje modal
     */
    function closeModal() {
        if (!modalElement) return;

        modalElement.classList.remove('is-open');
        document.body.style.overflow = '';

        // Odota transition ennen sisällön tyhjennystä
        closeTimer = setTimeout(function() {
            closeTimer = null;
            if (!modalElement) return;
            const content = modalElement.querySelector('.tjobs-modal__content');
            if (content) {
                content.innerHTML = '';
            }
        }, 300);
    }

    /**
     * Luo modal DOM-rakenne
     */
    function createModalDOM() {
        modalElement = document.createElement('div');
        modalElement.className = 'tjobs-modal-overlay';
        modalElement.innerHTML = `
            <div class="tjobs-modal-panel">
                <div class="tjobs-modal__content"></div>
            </div>
        `;

        document.body.appendChild(modalElement);

        // Overlay-klikkaus sulkee
        modalElement.addEventListener('click', function(e) {
            if (e.target.classList.contains('tjobs-modal-overlay')) {
                closeModal();
            }
        });
    }

    /**
     * Lataa työpaikan data REST API:sta
     */
    function loadJobData(jobId, lang) {
        const content = modalElement.querySelector('.tjobs-modal__content');
        if (!content) return;

        // Näytä loading-spinner
        i18n = window.tjobsModalConfig && window.tjobsModalConfig.i18n ? window.tjobsModalConfig.i18n : {};
        content.innerHTML = `
            <div class="tjobs-modal__loading">
                <div class="tjobs-spinner"></div>
                <p>${i18n['modal.loading'] || 'Ladataan...'}</p>
            </div>
        `;

        // Hae data
        const restUrl = window.tjobsModalConfig ? window.tjobsModalConfig.restUrl : '/wp-json/tjobs/v1';
        const url = `${restUrl}/job-info/${jobId}?lang=${lang}`;

        fetch(url)
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(data => {
                // Varmista modal on yhä olemassa ja DOM:ssa ennen renderöintiä
                if (!modalElement || !isInDocument(modalElement)) return;
                i18n = data.i18n || i18n;

                // Jos infopakettia ei löydy, sulje modal ja ohjaa suoraan hakemukseen
                if (!data.infopackage) {
                    const targetUrl = data.apply_url || currentApplyUrl;
                    closeModal();
                    if (targetUrl && /^https?:\/\//i.test(targetUrl)) {
                        if (!window.open(targetUrl, '_blank', 'noopener,noreferrer')) {
                            window.location.href = targetUrl;
                        }
                    }
                    return;
                }

                renderJobInfo(data);
            })
            .catch(error => {
                console.error('TJobs modal: Error loading job info:', error);
                // Varmista modal on yhä olemassa ennen virheviestinäyttöä
                if (!modalElement || !isInDocument(modalElement)) return;
                const errorContent = modalElement.querySelector('.tjobs-modal__content');
                if (!errorContent) return;
                errorContent.innerHTML = `
                    <div class="tjobs-modal__error">
                        <p>${i18n['modal.load_error'] || 'Tietojen lataaminen epäonnistui.'}</p>
                        <button type="button" class="tjobs-modal__retry">
                            ${i18n['modal.close'] || 'Sulje'}
                        </button>
                    </div>
                `;
                
                const retryBtn = errorContent.querySelector('.tjobs-modal__retry');
                if (retryBtn) {
                    retryBtn.addEventListener('click', closeModal);
                }
            });
    }

    /**
     * Renderöi työpaikan tiedot tab-pohjaisena
     */
    function renderJobInfo(data) {
        const content = modalElement.querySelector('.tjobs-modal__content');
        if (!content) return;

        try {
            renderJobInfoInner(data, content);
        } catch (error) {
            console.error('TJobs modal: renderJobInfo failed', error);
            const applyLink = data && data.apply_url
                ? '<a href="' + escapeHtml(data.apply_url) + '" target="_blank" rel="noopener" class="tjobs-cta-button">' + (i18n['modal.cta_apply'] || 'Siirry hakemaan →') + '</a>'
                : '';
            content.innerHTML =
                '<div class="tjobs-modal__error">' +
                '<button type="button" class="tjobs-modal__close" aria-label="Close">&times;</button>' +
                '<p>' + (i18n['modal.load_error'] || 'Tietojen lataaminen epäonnistui.') + '</p>' +
                applyLink +
                '</div>';
            const closeBtn = content.querySelector('.tjobs-modal__close');
            if (closeBtn) { closeBtn.addEventListener('click', closeModal); }
        }
    }

    function renderJobInfoInner(data, content) {
        const pkg = data.infopackage;
        const hasDescription = data.description && data.description.trim();
        const hasMedia = pkg && ((pkg.video_url && pkg.video_url.trim()) || (pkg.gallery && pkg.gallery.length > 0));
        const hasQuestions = pkg && pkg.questions && pkg.questions.length > 0;
        const hasSections = pkg && pkg.sections && pkg.sections.length > 0;
        const showTabs = hasDescription || hasMedia || hasQuestions || hasSections;

        // Hae wizard-konfiguraatio
        const config = window.tjobsModalConfig || {};
        const configTabs = config.tabs && config.tabs.length
            ? config.tabs
            : [
                {id: 'announcement', label: 'tab.announcement'},
                {id: 'general',      label: 'tab.general'},
                {id: 'videos',       label: 'tab.videos'},
                {id: 'details',      label: 'tab.details'},
                {id: 'questions',    label: 'tab.questions'}
              ];

        wizardForceLinear = config.forceLinear !== undefined ? !!config.forceLinear : true;
        wizardApplyUrl = data.apply_url || currentApplyUrl || '';

        // Sisällön saatavuus per välilehti
        const contentAvailable = {
            announcement: !!hasDescription,
            general:      !!pkg,
            videos:       !!hasMedia,
            details:      !!hasSections,
            questions:    !!hasQuestions
        };

        // Rakenna wizard-askeleet konfiguraation järjestyksessä, vain olemassa oleva sisältö
        wizardSteps = configTabs
            .map(t => t.id)
            .filter(id => contentAvailable[id] !== undefined && contentAvailable[id]);

        // Varmista, että vähintään yksi askel
        if (wizardSteps.length === 0) {
            wizardSteps = ['general'];
        }

        // Aseta alkuaskel: ensimmäinen käytettävissä oleva vaihe
        wizardCurrentIndex = 0;
        wizardVisited = new Set([0]);
        currentTab = wizardSteps[0] || 'announcement';

        let html = '';

        // Top bar: sulkemisnappi ja kielivalitsin
        html += '<div class="tjobs-modal__topbar">';
        html += '<button type="button" class="tjobs-modal__close" aria-label="Close">&times;</button>';
        
        // Kielivalitsin
        if (pkg && pkg.available_languages) {
            const availableLangs = Object.keys(pkg.available_languages).filter(lang => 
                pkg.available_languages[lang]
            );
            
            if (availableLangs.length > 1) {
                html += '<div class="tjobs-modal__lang-switcher">';
                availableLangs.forEach(lang => {
                    const isActive = lang === data.lang;
                    html += `<button type="button" class="tjobs-lang-btn ${isActive ? 'is-active' : ''}" data-lang="${lang}">${lang.toUpperCase()}</button>`;
                });
                html += '</div>';
            }
        }
        
        html += '</div>';

        // Otsikko
        html += `<h2 class="tjobs-modal__title">${escapeHtml(data.title)}</h2>`;

        // Excerpt
        if (data.excerpt) {
            html += `<div class="tjobs-modal__excerpt">${escapeHtml(data.excerpt)}</div>`;
        }

        // === WIZARD PROGRESS BAR ===
        if (showTabs && wizardSteps.length > 0) {
            const totalSteps = wizardSteps.length;
            const pct = totalSteps > 1 ? Math.round((0 / (totalSteps - 1)) * 100) : 100;

            html += '<div class="tjobs-wizard-header">';

            // Progress bar
            html += `<div class="tjobs-progress" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="${pct}" aria-label="${i18n['wizard.step_of'] ? i18n['wizard.step_of'].replace('%1$d', 1).replace('%2$d', totalSteps) : '1 / ' + totalSteps}">`;
            html += '<div class="tjobs-progress__track">';
            html += `<div class="tjobs-progress__fill" style="width:${pct}%"></div>`;
            html += '</div>';
            html += '</div>';

            // Step dots
            if (totalSteps > 1) {
                html += '<div class="tjobs-step-dots" role="list">';
                wizardSteps.forEach((stepId, idx) => {
                    const tabObj = configTabs.find(t => t.id === stepId) || {label: 'tab.' + stepId};
                    const stepLabel = i18n[tabObj.label] || stepId;
                    const isActive = idx === 0;
                    const dotClass = isActive ? 'tjobs-step-dot is-active' : 'tjobs-step-dot';
                    const ariaCurrent = isActive ? ' aria-current="step"' : '';
                    const disabled = (wizardForceLinear && !wizardVisited.has(idx)) ? ' disabled' : '';
                    html += `<button type="button" class="${dotClass}" data-step-index="${idx}" aria-label="${escapeHtml(stepLabel)}"${ariaCurrent}${disabled} role="listitem">`;
                    html += `<span class="tjobs-step-dot__circle" aria-hidden="true">${idx + 1}</span>`;
                    html += `<span class="tjobs-step-dot__label">${escapeHtml(stepLabel)}</span>`;
                    html += '</button>';
                });
                html += '</div>';
            }

            // Vaihe X / Y -teksti
            const stepOfText = i18n['wizard.step_of']
                ? i18n['wizard.step_of'].replace('%1$d', 1).replace('%2$d', totalSteps)
                : `1 / ${totalSteps}`;
            html += `<div class="tjobs-wizard-step-counter" aria-live="polite" aria-atomic="true">${escapeHtml(stepOfText)}</div>`;

            html += '</div>'; // .tjobs-wizard-header
        }

        // Tab-sisältö: Ilmoitus (työpaikkailmoitus RSS:stä)
        if (hasDescription) {
            html += '<div class="tjobs-tab-content" data-tab-content="announcement">';
            // Note: Server sanitizes with wp_kses_post (WordPress standard for post content)
            // This is the same sanitization used for all WordPress post content and is safe to render as HTML
            html += `<div class="tjobs-modal__job-description">${data.description}</div>`;
            html += '</div>';
        }

        // Tab-sisältö: Yleistä
        html += `<div class="tjobs-tab-content" data-tab-content="general"${hasDescription ? ' style="display:none;"' : ''}>`;        
        if (pkg) {
            // Highlights
            if (pkg.highlights && pkg.highlights.length > 0) {
                html += '<div class="tjobs-modal__highlights">';
                pkg.highlights.forEach(highlight => {
                    html += `<span class="tjobs-highlight-pill">${escapeHtml(highlight)}</span>`;
                });
                html += '</div>';
            }

            // Intro
            if (pkg.intro) {
                html += `<div class="tjobs-modal__intro">${escapeHtml(pkg.intro)}</div>`;
            }

            // Yhteyshenkilö
            if (pkg.contact && (pkg.contact.name || pkg.contact.email || pkg.contact.phone)) {
                html += `<h3 class="tjobs-modal__section-heading">${i18n['modal.contact_heading'] || 'Yhteyshenkilö'}</h3>`;
                html += '<div class="tjobs-modal__contact">';
                if (pkg.contact.name) {
                    html += `<p><strong>👤 ${escapeHtml(pkg.contact.name)}</strong></p>`;
                }
                if (pkg.contact.email) {
                    html += `<p>📧 <a href="mailto:${escapeHtml(pkg.contact.email)}">${escapeHtml(pkg.contact.email)}</a></p>`;
                }
                if (pkg.contact.phone) {
                    html += `<p>📱 ${escapeHtml(pkg.contact.phone)}</p>`;
                }
                html += '</div>';
            }
        }
        html += '</div>';

        // Tab-sisältö: Media (rekisteriavain: videos)
        if (hasMedia) {
            html += `<div class="tjobs-tab-content" data-tab-content="videos" style="display:none;">`;
            
            // Video
            if (pkg.video_url && pkg.video_url.trim()) {
                const embedUrl = parseVideoUrl(pkg.video_url);
                if (embedUrl) {
                    html += '<div class="tjobs-modal__video-wrapper">';
                    html += `<iframe src="${escapeHtml(embedUrl)}" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen loading="lazy"></iframe>`;
                    html += '</div>';
                }
            }

            // Galleria
            if (pkg.gallery && pkg.gallery.length > 0) {
                html += '<div class="tjobs-modal__gallery">';
                pkg.gallery.forEach((image, index) => {
                    html += `<div class="tjobs-gallery-item" data-index="${index}">`;
                    html += `<img src="${escapeHtml(image.thumb)}" alt="" loading="lazy" />`;
                    html += '</div>';
                });
                html += '</div>';
            }

            html += '</div>';
        }

        // Tab-sisältö: Lisätiedot
        if (hasSections) {
            html += '<div class="tjobs-tab-content" data-tab-content="details" style="display:none;">';
            pkg.sections.forEach(section => {
                html += '<div class="tjobs-modal__info-section">';
                if (section.icon || section.title) {
                    html += '<h3 class="tjobs-modal__info-section-heading">';
                    if (section.icon) {
                        html += '<span class="tjobs-modal__info-section-icon">' + escapeHtml(section.icon) + '</span>';
                    }
                    if (section.title) {
                        html += '<span>' + escapeHtml(section.title) + '</span>';
                    }
                    html += '</h3>';
                }
                if (section.content) {
                    html += '<div class="tjobs-modal__info-section-content">' + escapeHtml(section.content) + '</div>';
                }
                html += '</div>';
            });
            html += '</div>';
        }

        // Tab-sisältö: Kysymykset
        if (hasQuestions) {
            html += `<div class="tjobs-tab-content" data-tab-content="questions" style="display:none;">`;
            html += '<div class="tjobs-modal__questions">';
            pkg.questions.forEach((q, index) => {
                html += renderQuestion(q, index);
            });
            html += '</div>';
            html += '</div>';
        }

        // CTA-nappi: renderWizardState hallinnoi näkyvyyttä wizard-tilassa;
        // näytetään aina kun ei wizard-navigaatiota
        if (data.apply_url) {
            html += `<div class="tjobs-modal__cta">
                <a href="${escapeHtml(data.apply_url)}" target="_blank" rel="noopener" class="tjobs-cta-button">
                    ${i18n['modal.cta_apply'] || 'Siirry hakemaan →'}
                </a>
            </div>`;
        }

        // Wizard navigointipainikkeet (näytetään kun vaiheita on enemmän kuin yksi)
        if (showTabs && wizardSteps.length > 1) {
            const prevLabel = i18n['wizard.prev'] || 'Edellinen';
            const nextLabel = (i18n['wizard.next'] || 'Seuraava') + ' →';
            html += '<div class="tjobs-wizard-nav">';
            // Edellinen-nappi (piilotettu ensimmäisellä askeleella)
            html += `<button type="button" class="tjobs-wizard-nav__prev" style="display:none;" aria-label="${escapeHtml(prevLabel)}">← ${escapeHtml(prevLabel)}</button>`;
            // Seuraava-nappi
            html += `<button type="button" class="tjobs-wizard-nav__next" aria-label="${escapeHtml(nextLabel)}">${escapeHtml(nextLabel)}</button>`;
            html += '</div>';
        }

        content.innerHTML = html;

        // Aktivoi ensimmäinen askel
        if (showTabs && wizardSteps.length > 0) {
            renderWizardState(content, data.apply_url);
        }

        // Event listenerit
        attachEventListeners(data);
    }

    /**
     * Renderöi wizard-tila: päivitä progress bar, step dots, sisältö ja navigointipainikkeet.
     */
    function renderWizardState(contentEl, applyUrl) {
        if (!contentEl) {
            contentEl = modalElement && modalElement.querySelector('.tjobs-modal__content');
        }
        if (!contentEl || wizardSteps.length === 0) { return; }

        const totalSteps = wizardSteps.length;
        const pct = totalSteps > 1 ? Math.round((wizardCurrentIndex / (totalSteps - 1)) * 100) : 100;
        const isFirst = wizardCurrentIndex === 0;
        const isLast  = wizardCurrentIndex === totalSteps - 1;

        // Progress bar
        const progressBar  = contentEl.querySelector('.tjobs-progress');
        const progressFill = contentEl.querySelector('.tjobs-progress__fill');
        if (progressBar) {
            progressBar.setAttribute('aria-valuenow', pct);
            const stepOfText = i18n['wizard.step_of']
                ? i18n['wizard.step_of'].replace('%1$d', wizardCurrentIndex + 1).replace('%2$d', totalSteps)
                : `${wizardCurrentIndex + 1} / ${totalSteps}`;
            progressBar.setAttribute('aria-label', stepOfText);
        }
        if (progressFill) {
            progressFill.style.width = pct + '%';
        }

        // Step dots
        const dots = contentEl.querySelectorAll('.tjobs-step-dot');
        dots.forEach(function(dot, idx) {
            dot.classList.remove('is-active', 'is-done');
            dot.removeAttribute('aria-current');
            if (idx < wizardCurrentIndex) {
                dot.classList.add('is-done');
            } else if (idx === wizardCurrentIndex) {
                dot.classList.add('is-active');
                dot.setAttribute('aria-current', 'step');
            }
            if (wizardForceLinear) {
                dot.disabled = !wizardVisited.has(idx);
            } else {
                dot.disabled = false;
            }
        });

        // Vaihe X / Y -teksti
        const counter = contentEl.querySelector('.tjobs-wizard-step-counter');
        if (counter) {
            const stepOfText = i18n['wizard.step_of']
                ? i18n['wizard.step_of'].replace('%1$d', wizardCurrentIndex + 1).replace('%2$d', totalSteps)
                : `${wizardCurrentIndex + 1} / ${totalSteps}`;
            counter.textContent = stepOfText;
        }

        // Näytä aktiivinen tab-sisältö
        const tabContents = contentEl.querySelectorAll('.tjobs-tab-content');
        tabContents.forEach(function(tc) {
            const tabId = tc.getAttribute('data-tab-content');
            tc.style.display = (tabId === wizardSteps[wizardCurrentIndex]) ? 'block' : 'none';
        });
        currentTab = wizardSteps[wizardCurrentIndex];

        // Navigointipainikkeet
        const prevBtn = contentEl.querySelector('.tjobs-wizard-nav__prev');
        const nextBtn = contentEl.querySelector('.tjobs-wizard-nav__next');
        const ctaSection = contentEl.querySelector('.tjobs-modal__cta');

        if (prevBtn) {
            prevBtn.style.display = isFirst ? 'none' : 'inline-flex';
            prevBtn.disabled = isFirst;
        }

        if (nextBtn) {
            if (wizardForceLinear && isLast) {
                // Viimeisellä askeleella: Next muuttuu Apply-CTA:ksi
                const applyText = i18n['modal.cta_apply'] || 'Siirry hakemaan →';
                nextBtn.textContent = applyText;
                nextBtn.classList.add('is-apply', 'tjobs-cta-button');
                nextBtn.setAttribute('data-apply-href', applyUrl || wizardApplyUrl || '');
            } else {
                const nextText = (i18n['wizard.next'] || 'Seuraava') + ' →';
                nextBtn.textContent = nextText;
                nextBtn.classList.remove('is-apply', 'tjobs-cta-button');
                nextBtn.removeAttribute('data-apply-href');
            }
        }

        // CTA-osio: forceLinear=true ja useita vaiheita → piilotettu (Apply on Next-napilla);
        // muussa tapauksessa aina näkyvissä (yksittäinen vaihe tai vapaa selailu)
        if (ctaSection) {
            const hasWizardNav = wizardSteps.length > 1;
            const showCta = !wizardForceLinear || !hasWizardNav;
            ctaSection.style.display = showCta ? 'block' : 'none';
        }

        // Focus management: siirrä fokus aktiivisen vaiheen otsikkoon
        const activeContent = contentEl.querySelector(`.tjobs-tab-content[data-tab-content="${wizardSteps[wizardCurrentIndex]}"]`);
        if (activeContent) {
            const heading = activeContent.querySelector('h2, h3');
            const focusTarget = heading || activeContent;
            if (focusTarget) {
                focusTarget.setAttribute('tabindex', '-1');
                focusTarget.focus({ preventScroll: false });
            }
        }
    }

    /**
     * Kiinnitä event listenerit modaliin
     */
    function attachEventListeners(data) {
        const content = modalElement.querySelector('.tjobs-modal__content');

        // Sulkemisnappi
        const closeBtn = content.querySelector('.tjobs-modal__close');
        if (closeBtn) {
            closeBtn.addEventListener('click', closeModal);
        }

        // Kielivalitsimet
        const langButtons = content.querySelectorAll('.tjobs-lang-btn');
        langButtons.forEach(btn => {
            btn.addEventListener('click', function() {
                const newLang = this.getAttribute('data-lang');
                if (newLang && newLang !== currentLang) {
                    openModal(currentJobId, newLang, currentApplyUrl);
                }
            });
        });

        // Wizard: step dots klikkaus
        const stepDots = content.querySelectorAll('.tjobs-step-dot');
        stepDots.forEach(function(dot) {
            dot.addEventListener('click', function() {
                const idx = parseInt(this.getAttribute('data-step-index'), 10);
                if (isNaN(idx)) { return; }
                if (wizardForceLinear && !wizardVisited.has(idx)) { return; }
                wizardCurrentIndex = idx;
                wizardVisited.add(idx);
                renderWizardState(content, data.apply_url);
            });
        });

        // Wizard: Edellinen-nappi
        const prevBtn = content.querySelector('.tjobs-wizard-nav__prev');
        if (prevBtn) {
            prevBtn.addEventListener('click', function() {
                if (wizardCurrentIndex > 0) {
                    wizardCurrentIndex--;
                    renderWizardState(content, data.apply_url);
                }
            });
        }

        // Wizard: Seuraava / Apply -nappi
        const nextBtn = content.querySelector('.tjobs-wizard-nav__next');
        if (nextBtn) {
            nextBtn.addEventListener('click', function() {
                if (this.classList.contains('is-apply')) {
                    // Viimeinen askel → avaa apply URL (validoitu URL API:lla)
                    const rawHref = this.getAttribute('data-apply-href') || data.apply_url || wizardApplyUrl;
                    try {
                        const parsedUrl = new URL(String(rawHref));
                        if (parsedUrl.protocol !== 'https:' && parsedUrl.protocol !== 'http:') {
                            return; // Hylkää javascript: ja muut protokollat
                        }
                        const safeHref = parsedUrl.href;
                        closeModal();
                        if (!window.open(safeHref, '_blank', 'noopener,noreferrer')) {
                            window.location.assign(safeHref);
                        }
                    } catch (e) {
                        // Virheellinen URL – ei tehdä mitään
                    }
                } else if (wizardCurrentIndex < wizardSteps.length - 1) {
                    wizardCurrentIndex++;
                    wizardVisited.add(wizardCurrentIndex);
                    renderWizardState(content, data.apply_url);
                }
            });
        }

        // Galleria-kuvien klikkaukset (lightbox)
        const galleryItems = content.querySelectorAll('.tjobs-gallery-item');
        if (data.infopackage && data.infopackage.gallery) {
            galleryItems.forEach(item => {
                item.addEventListener('click', function() {
                    const index = parseInt(this.getAttribute('data-index'), 10);
                    openLightbox(data.infopackage.gallery, index);
                });
            });
        }

        // Pre-sort score feedback rules once (highest min_errors first) for matching in checkOverallResult
        const scoreFeedbackRules = data.infopackage && data.infopackage.score_feedback_rules
            ? data.infopackage.score_feedback_rules.slice().sort(function(a, b) { return b.min_errors - a.min_errors; })
            : [];

        // Yes/No pill buttons
        const pillButtons = content.querySelectorAll('.tjobs-pill-button');
        pillButtons.forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.stopPropagation();
                const siblings = this.parentElement.querySelectorAll('.tjobs-pill-button');
                siblings.forEach(s => s.classList.remove('is-selected'));
                this.classList.add('is-selected');

                // Preserve current tab active state
                switchTab(currentTab);

                checkOverallResult(content, scoreFeedbackRules);
            });
        });

        // Scale radio buttons
        const scaleInputs = content.querySelectorAll('.tjobs-question__scale input[type="radio"]');
        scaleInputs.forEach(input => {
            input.addEventListener('change', function(e) {
                e.stopPropagation();
                checkOverallResult(content, scoreFeedbackRules);
            });
        });

        // Select dropdown
        const selectInputs = content.querySelectorAll('.tjobs-question__select');
        selectInputs.forEach(select => {
            select.addEventListener('change', function(e) {
                e.stopPropagation();
                checkOverallResult(content, scoreFeedbackRules);
            });
        });
    }

    /**
     * Vaihda aktiivista tabia
     */
    function switchTab(tabName) {
        currentTab = tabName;
        
        const content = modalElement.querySelector('.tjobs-modal__content');
        
        // Päivitä tab-napit
        const tabButtons = content.querySelectorAll('.tjobs-tab-btn');
        tabButtons.forEach(btn => {
            if (btn.getAttribute('data-tab') === tabName) {
                btn.classList.add('is-active');
            } else {
                btn.classList.remove('is-active');
            }
        });

        // Päivitä tab-sisällöt
        const tabContents = content.querySelectorAll('.tjobs-tab-content');
        tabContents.forEach(tc => {
            if (tc.getAttribute('data-tab-content') === tabName) {
                tc.style.display = 'block';
            } else {
                tc.style.display = 'none';
            }
        });
    }

    /**
     * Avaa lightbox kuvagallerialle
     */
    function openLightbox(gallery, startIndex) {
        if (!lightboxElement) {
            createLightboxDOM();
        }

        const image = lightboxElement.querySelector('.tjobs-lightbox__image');
        const counter = lightboxElement.querySelector('.tjobs-lightbox__counter');
        
        let currentIndex = startIndex;

        function showImage(index) {
            currentIndex = index;
            image.src = gallery[index].url;
            counter.textContent = `${index + 1} / ${gallery.length}`;
        }

        // Navigointi
        const prevBtn = lightboxElement.querySelector('.tjobs-lightbox__prev');
        const nextBtn = lightboxElement.querySelector('.tjobs-lightbox__next');

        prevBtn.onclick = function() {
            const newIndex = (currentIndex - 1 + gallery.length) % gallery.length;
            showImage(newIndex);
        };

        nextBtn.onclick = function() {
            const newIndex = (currentIndex + 1) % gallery.length;
            showImage(newIndex);
        };

        showImage(startIndex);
        lightboxElement.classList.add('is-open');
        document.body.style.overflow = 'hidden';
    }

    /**
     * Sulje lightbox
     */
    function closeLightbox() {
        if (!lightboxElement) return;
        lightboxElement.classList.remove('is-open');
        // Don't restore body overflow here - modal is still open
    }

    /**
     * Luo lightbox DOM
     */
    function createLightboxDOM() {
        lightboxElement = document.createElement('div');
        lightboxElement.className = 'tjobs-lightbox-overlay';
        lightboxElement.innerHTML = `
            <button type="button" class="tjobs-lightbox__close" aria-label="Close">&times;</button>
            <button type="button" class="tjobs-lightbox__prev" aria-label="Previous">‹</button>
            <button type="button" class="tjobs-lightbox__next" aria-label="Next">›</button>
            <div class="tjobs-lightbox__content">
                <img class="tjobs-lightbox__image" src="" alt="" />
                <div class="tjobs-lightbox__counter">1 / 1</div>
            </div>
        `;

        document.body.appendChild(lightboxElement);

        // Sulkemisnappi
        const closeBtn = lightboxElement.querySelector('.tjobs-lightbox__close');
        closeBtn.addEventListener('click', closeLightbox);

        // Overlay-klikkaus sulkee
        lightboxElement.addEventListener('click', function(e) {
            if (e.target.classList.contains('tjobs-lightbox-overlay')) {
                closeLightbox();
            }
        });
    }

    /**
     * Parsii video URL:n embed-muotoon
     * Validates and converts YouTube/Vimeo URLs to embed format
     */
    function parseVideoUrl(url) {
        if (!url) return null;

        url = url.trim();

        // YouTube - validate and extract video ID
        let match = url.match(/(?:youtube\.com\/watch\?v=|youtu\.be\/)([a-zA-Z0-9_-]{11})/);
        if (match) {
            return `https://www.youtube.com/embed/${match[1]}`;
        }

        // Vimeo - validate and extract video ID
        match = url.match(/vimeo\.com\/(\d+)/);
        if (match) {
            return `https://player.vimeo.com/video/${match[1]}`;
        }

        // Validate if already in embed format
        if (url.match(/^https:\/\/(www\.youtube\.com\/embed\/[a-zA-Z0-9_-]{11}|player\.vimeo\.com\/video\/\d+)$/)) {
            return url;
        }

        return null;
    }

    /**
     * Renderöi yksittäinen kysymys
     */
    function renderQuestion(question, index) {
        let html = '<div class="tjobs-question">';
        
        // Kysymysteksti
        html += `<label class="tjobs-question__label">
            ${escapeHtml(question.question)}
            ${question.required ? `<span class="tjobs-required">*</span>` : ''}
        </label>`;

        // Tyyppikohtainen renderöinti
        switch (question.type) {
            case 'text':
                html += `<textarea class="tjobs-question__textarea" placeholder="${i18n['question.text_placeholder'] || 'Kirjoita vastauksesi tähän'}" ${question.required ? 'required' : ''}></textarea>`;
                break;

            case 'yesno':
                html += '<div class="tjobs-question__pills">';
                html += `<button type="button" class="tjobs-pill-button" data-value="yes">${i18n['question.yes'] || 'Kyllä'}</button>`;
                html += `<button type="button" class="tjobs-pill-button" data-value="no">${i18n['question.no'] || 'Ei'}</button>`;
                html += '</div>';
                break;

            case 'scale':
                html += '<div class="tjobs-question__scale">';
                for (let i = 1; i <= 5; i++) {
                    html += `<label class="tjobs-scale-option">
                        <input type="radio" name="question_${index}" value="${i}" ${question.required ? 'required' : ''}>
                        <span>${i}</span>
                    </label>`;
                }
                html += '</div>';
                break;

            case 'select':
                html += `<select class="tjobs-question__select" ${question.required ? 'required' : ''}>`;
                html += `<option value="">${i18n['question.select_placeholder'] || 'Valitse...'}</option>`;
                if (question.options) {
                    const options = question.options.split('\n');
                    options.forEach(opt => {
                        const trimmed = opt.trim();
                        if (trimmed) {
                            html += `<option value="${escapeHtml(trimmed)}">${escapeHtml(trimmed)}</option>`;
                        }
                    });
                }
                html += '</select>';
                break;

            case 'info':
                // Pelkkä teksti, ei input-kenttää
                break;

            default:
                html += `<input type="text" class="tjobs-question__input" ${question.required ? 'required' : ''}>`;
        }

        // Palaute-banneri placeholder (piilotettu oletuksena)
        // Null-suojaukset varmistavat yhteensopivuuden vanhan datan kanssa
        const unsuitableValue = question.unsuitable_value || '';
        const unsuitableFeedback = question.unsuitable_feedback || '';
        
        if (unsuitableValue && unsuitableFeedback) {
            html += `<div class="tjobs-question__feedback" style="display:none;" data-unsuitable-values="${escapeHtml(unsuitableValue)}">
                <div class="tjobs-feedback-banner">
                    <span class="tjobs-feedback-icon">💡</span>
                    <div class="tjobs-feedback-text">
                        <strong>${i18n['feedback.heading'] || 'Huomio'}</strong>
                        <p>${escapeHtml(unsuitableFeedback)}</p>
                    </div>
                </div>
            </div>`;
        } else if (unsuitableValue) {
            // Käytä oletuspalautetta
            html += `<div class="tjobs-question__feedback" style="display:none;" data-unsuitable-values="${escapeHtml(unsuitableValue)}">
                <div class="tjobs-feedback-banner">
                    <span class="tjobs-feedback-icon">💡</span>
                    <div class="tjobs-feedback-text">
                        <strong>${i18n['feedback.heading'] || 'Huomio'}</strong>
                        <p>${i18n['feedback.unsuitable_default'] || 'Tämä tehtävä ei välttämättä vastaa kaikkia toiveitasi, mutta voit silti jatkaa hakemista!'}</p>
                    </div>
                </div>
            </div>`;
        }

        html += '</div>';
        return html;
    }

    /**
     * Tarkista kokonaistulos ja näytä tulos-banneri + yksilöllinen palaute.
     * Banneri näytetään kun kaikki ei-info-kysymykset on vastattu.
     *
     * @param {Element} contentEl          - Modal content element
     * @param {Array}   scoreFeedbackRules - Array of {min_errors, message} objects
     */
    function checkOverallResult(contentEl, scoreFeedbackRules) {
        if (!contentEl) return;

        var questionsContainer = contentEl.querySelector('.tjobs-modal__questions');
        if (!questionsContainer) return;

        var questionDivs = questionsContainer.querySelectorAll('.tjobs-question');
        if (questionDivs.length === 0) return;

        var totalAnswerable = 0;
        var totalAnswered = 0;
        var totalSuitable = 0;

        questionDivs.forEach(function(qDiv) {
            // Skip info-type questions (no interactive inputs)
            var hasAnyInput = qDiv.querySelector('textarea, input[type="radio"], input[type="text"], select, .tjobs-pill-button');
            if (!hasAnyInput) return;

            totalAnswerable++;

            var selectedValue = null;

            // Yes/No pill
            var selectedPill = qDiv.querySelector('.tjobs-pill-button.is-selected');
            if (selectedPill) {
                selectedValue = selectedPill.getAttribute('data-value');
            }

            // Radio (scale)
            var checkedRadio = qDiv.querySelector('input[type="radio"]:checked');
            if (checkedRadio) {
                selectedValue = checkedRadio.value;
            }

            // Select
            var selectEl = qDiv.querySelector('.tjobs-question__select');
            if (selectEl && selectEl.value) {
                selectedValue = selectEl.value;
            }

            // Textarea
            var textareaEl = qDiv.querySelector('.tjobs-question__textarea');
            if (textareaEl && textareaEl.value.trim()) {
                selectedValue = textareaEl.value.trim();
            }

            if (selectedValue === null || selectedValue === '') return;
            totalAnswered++;

            // Check if this answer is suitable (didn't hit unsuitable value)
            var feedbackDiv = qDiv.querySelector('.tjobs-question__feedback');
            if (feedbackDiv) {
                var unsuitableValuesAttr = feedbackDiv.getAttribute('data-unsuitable-values');
                if (unsuitableValuesAttr) {
                    var unsuitableValues = unsuitableValuesAttr
                        .split(',')
                        .map(function(v) { return v.trim().toLowerCase(); });
                    if (!unsuitableValues.includes(String(selectedValue).toLowerCase())) {
                        totalSuitable++;
                    }
                } else {
                    totalSuitable++;
                }
            } else {
                totalSuitable++;
            }
        });

        if (totalAnswerable === 0) return;

        // Remove existing banners
        var existingBanner = questionsContainer.querySelector('.tjobs-result-banner');
        if (existingBanner) { existingBanner.remove(); }

        var existingFeedbackSummary = questionsContainer.querySelector('.tjobs-feedback-summary');
        if (existingFeedbackSummary) { existingFeedbackSummary.remove(); }

        // Only show result when all answerable questions have been answered
        if (totalAnswered < totalAnswerable) return;

        var totalErrors = totalAnswerable - totalSuitable;

        // Find matching score rule (highest min_errors that is <= totalErrors, already pre-sorted)
        var matchedRule = null;
        if (scoreFeedbackRules && scoreFeedbackRules.length > 0) {
            for (var i = 0; i < scoreFeedbackRules.length; i++) {
                if (totalErrors >= scoreFeedbackRules[i].min_errors) {
                    matchedRule = scoreFeedbackRules[i];
                    break;
                }
            }
        }

        // Collect individual feedback messages for unsuitable answers
        var feedbackMessages = [];
        questionDivs.forEach(function(qDiv) {
            var feedbackDiv = qDiv.querySelector('.tjobs-question__feedback');
            if (!feedbackDiv) return;
            var unsuitableValuesAttr = feedbackDiv.getAttribute('data-unsuitable-values');
            if (!unsuitableValuesAttr) return;

            var selectedValue = null;
            var selectedPill = qDiv.querySelector('.tjobs-pill-button.is-selected');
            if (selectedPill) selectedValue = selectedPill.getAttribute('data-value');
            var checkedRadio = qDiv.querySelector('input[type="radio"]:checked');
            if (checkedRadio) selectedValue = checkedRadio.value;
            var selectEl = qDiv.querySelector('.tjobs-question__select');
            if (selectEl && selectEl.value) selectedValue = selectEl.value;

            if (selectedValue === null || selectedValue === '') return;

            var unsuitableValues = unsuitableValuesAttr.split(',').map(function(v) { return v.trim().toLowerCase(); });
            if (unsuitableValues.includes(String(selectedValue).toLowerCase())) {
                var feedbackTextEl = feedbackDiv.querySelector('.tjobs-feedback-text p');
                if (feedbackTextEl && feedbackTextEl.textContent.trim()) {
                    feedbackMessages.push(feedbackTextEl.textContent.trim());
                }
            }
        });

        // Build and append result banner
        var banner = document.createElement('div');
        if (matchedRule) {
            banner.className = 'tjobs-result-banner tjobs-result-banner--guidance';
            banner.innerHTML =
                '<span class="tjobs-result-banner__icon">&#x1F4A1;</span>' +
                '<div class="tjobs-result-banner__body">' +
                    '<p class="tjobs-result-banner__heading">' + escapeHtml(i18n['result.guidance_heading'] || 'Huomioi tehtävän vaatimukset') + '</p>' +
                    '<p class="tjobs-result-banner__text">' + escapeHtml(matchedRule.message) + '</p>' +
                '</div>';
        } else if (totalErrors === 0) {
            banner.className = 'tjobs-result-banner tjobs-result-banner--good';
            banner.innerHTML =
                '<span class="tjobs-result-banner__icon">✅</span>' +
                '<div class="tjobs-result-banner__body">' +
                    '<p class="tjobs-result-banner__heading">' + escapeHtml(i18n['result.good_heading'] || 'Hienoa!') + '</p>' +
                    '<p class="tjobs-result-banner__text">' + escapeHtml(i18n['result.good_text'] || 'Vaikutat sopivalta tähän tehtävään.') + '</p>' +
                '</div>';
        } else {
            banner.className = 'tjobs-result-banner tjobs-result-banner--guidance';
            banner.innerHTML =
                '<span class="tjobs-result-banner__icon">&#x1F4A1;</span>' +
                '<div class="tjobs-result-banner__body">' +
                    '<p class="tjobs-result-banner__heading">' + escapeHtml(i18n['result.guidance_heading'] || 'Huomioi tehtävän vaatimukset') + '</p>' +
                    '<p class="tjobs-result-banner__text">' + escapeHtml(i18n['result.guidance_text'] || 'Suosittelemme tutustumaan tarkemmin tehtävän vaatimuksiin. Voit kuitenkin jatkaa hakemista!') + '</p>' +
                '</div>';
        }

        questionsContainer.appendChild(banner);

        // Show individual feedback messages if any
        if (feedbackMessages.length > 0) {
            var feedbackSummary = document.createElement('div');
            feedbackSummary.className = 'tjobs-feedback-summary';
            var feedbackHtml = '<ul class="tjobs-feedback-list">';
            feedbackMessages.forEach(function(msg) {
                feedbackHtml += '<li>' + escapeHtml(msg) + '</li>';
            });
            feedbackHtml += '</ul>';
            feedbackSummary.innerHTML = feedbackHtml;
            questionsContainer.appendChild(feedbackSummary);
        }
    }

    /**
     * Escape HTML
     */
    function escapeHtml(text) {
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return String(text).replace(/[&<>"']/g, m => map[m]);
    }

    // Alusta kun DOM on valmis
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    /**
     * Julkinen nimiavaruus lisäosan eristämiseksi.
     * Muut skriptit voivat käyttää näitä metodeja tai tarkistaa
     * window.TJobsModalInitialized ennen omien toimiensa aloittamista.
     *
     * @namespace window.TJobsModal
     * @property {Function} openModal  - Avaa modal annetulla työpaikka-ID:llä ja kielellä.
     * @property {Function} closeModal - Sulkee avoinna olevan modalin.
     */
    window.TJobsModal = window.TJobsModal || {};
    window.TJobsModal.openModal = openModal;
    window.TJobsModal.closeModal = closeModal;

})();
