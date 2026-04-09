/**
 * Frontend Modal JavaScript - Tab-based Redesign
 * Luo slide-over modal työpaikan infopaketin näyttämiseen
 * Tuki: Tabit, Video, Kuvagalleria, Lightbox, Mobiiliresponsiivisuus
 */
(function() {
    'use strict';

    let currentJobId = null;
    let currentLang = null;
    let modalElement = null;
    let lightboxElement = null;
    let i18n = {};
    let currentTab = 'general';

    /**
     * Alusta modal
     */
    function init() {
        // Käytä event delegation job-linkkien klikkauksiin
        document.addEventListener('click', function(e) {
            const jobLink = e.target.closest('[data-job-id]');
            if (jobLink) {
                e.preventDefault();
                const jobId = jobLink.getAttribute('data-job-id');
                if (jobId) {
                    openModal(jobId);
                }
            }
        });

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
    }

    /**
     * Avaa modal
     */
    function openModal(jobId, lang) {
        currentJobId = jobId;
        currentLang = lang || (window.tjobsModalConfig ? window.tjobsModalConfig.lang : 'fi');
        currentTab = 'general'; // Reset to first tab

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
        setTimeout(function() {
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
                i18n = data.i18n || i18n;
                renderJobInfo(data);
            })
            .catch(error => {
                console.error('Error loading job info:', error);
                content.innerHTML = `
                    <div class="tjobs-modal__error">
                        <p>${i18n['modal.load_error'] || 'Tietojen lataaminen epäonnistui.'}</p>
                        <button type="button" class="tjobs-modal__retry">
                            ${i18n['modal.close'] || 'Sulje'}
                        </button>
                    </div>
                `;
                
                const retryBtn = content.querySelector('.tjobs-modal__retry');
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

        const pkg = data.infopackage;
        const hasMedia = pkg && ((pkg.video_url && pkg.video_url.trim()) || (pkg.gallery && pkg.gallery.length > 0));
        const hasQuestions = pkg && pkg.questions && pkg.questions.length > 0;
        const showTabs = hasMedia || hasQuestions;

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

        // Tabit (jos tarvitaan)
        if (showTabs) {
            html += '<div class="tjobs-modal__tabs">';
            html += `<button type="button" class="tjobs-tab-btn is-active" data-tab="general">${i18n['tab.general'] || 'Yleistä'}</button>`;
            if (hasMedia) {
                html += `<button type="button" class="tjobs-tab-btn" data-tab="media">${i18n['tab.videos'] || 'Videot'}</button>`;
            }
            if (hasQuestions) {
                html += `<button type="button" class="tjobs-tab-btn" data-tab="questions">${i18n['tab.questions'] || 'Kysymykset'}</button>`;
            }
            html += '</div>';
        }

        // Tab-sisältö: Yleistä
        html += `<div class="tjobs-tab-content" data-tab-content="general">`;
        
        // Työn kuvaus (laura:description)
        // Note: Server sanitizes with wp_kses_post (WordPress standard for post content)
        // This is the same sanitization used for all WordPress post content and is safe to render as HTML
        if (data.description) {
            html += '<div class="tjobs-modal__job-description">' + data.description + '</div>';
        }
        
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

        // Tab-sisältö: Media
        if (hasMedia) {
            html += `<div class="tjobs-tab-content" data-tab-content="media" style="display:none;">`;
            
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

        // CTA-nappi (sticky)
        if (data.apply_url) {
            html += `<div class="tjobs-modal__cta">
                <a href="${escapeHtml(data.apply_url)}" target="_blank" rel="noopener" class="tjobs-cta-button">
                    ${i18n['modal.cta_apply'] || 'Siirry hakemaan →'}
                </a>
            </div>`;
        }

        content.innerHTML = html;

        // Event listenerit
        attachEventListeners(data);
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
                    openModal(currentJobId, newLang);
                }
            });
        });

        // Tab-napit
        const tabButtons = content.querySelectorAll('.tjobs-tab-btn');
        tabButtons.forEach(btn => {
            btn.addEventListener('click', function() {
                const targetTab = this.getAttribute('data-tab');
                switchTab(targetTab);
            });
        });

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

        // Yes/No pill buttons
        const pillButtons = content.querySelectorAll('.tjobs-pill-button');
        pillButtons.forEach(btn => {
            btn.addEventListener('click', function() {
                const siblings = this.parentElement.querySelectorAll('.tjobs-pill-button');
                siblings.forEach(s => s.classList.remove('is-selected'));
                this.classList.add('is-selected');
                
                // Tarkista palaute
                const questionDiv = this.closest('.tjobs-question');
                checkUnsuitableFeedback(questionDiv, this.getAttribute('data-value'));
                checkOverallResult(content);
            });
        });

        // Scale radio buttons
        const scaleInputs = content.querySelectorAll('.tjobs-question__scale input[type="radio"]');
        scaleInputs.forEach(input => {
            input.addEventListener('change', function() {
                const questionDiv = this.closest('.tjobs-question');
                checkUnsuitableFeedback(questionDiv, this.value);
                checkOverallResult(content);
            });
        });

        // Select dropdown
        const selectInputs = content.querySelectorAll('.tjobs-question__select');
        selectInputs.forEach(select => {
            select.addEventListener('change', function() {
                const questionDiv = this.closest('.tjobs-question');
                checkUnsuitableFeedback(questionDiv, this.value);
                checkOverallResult(content);
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
     * Tarkista kokonaistulos (3/5 logiikka) ja näytä tulos-banneri.
     * Laskee kuinka moni vastaus on "sopiva" (ei osunut epäsopivuusarvoon).
     * Banneri näytetään kun kaikki ei-info-kysymykset on vastattu.
     *
     * @param {Element} contentEl - Modal content element
     */
    function checkOverallResult(contentEl) {
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

        // Remove existing result banner
        var existingBanner = questionsContainer.querySelector('.tjobs-result-banner');
        if (existingBanner) {
            existingBanner.remove();
        }

        // Only show result when all answerable questions have been answered
        if (totalAnswered < totalAnswerable) return;

        // 3/5 threshold: at least 3 suitable answers required (hakeminen ei koskaan esty)
        var THRESHOLD = 3;
        var isGood = totalSuitable >= THRESHOLD;

        var banner = document.createElement('div');
        if (isGood) {
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
    }

    /**
     * Tarkista epäsopiva palaute ja näytä banneri tarvittaessa
     */
    function checkUnsuitableFeedback(questionDiv, selectedValue) {
        if (!questionDiv) return;
        const feedbackDiv = questionDiv.querySelector('.tjobs-question__feedback');
        if (!feedbackDiv) return;
        
        const unsuitableValuesAttr = feedbackDiv.getAttribute('data-unsuitable-values');
        if (!unsuitableValuesAttr) return;
        
        const unsuitableValues = unsuitableValuesAttr
            .split(',')
            .map(v => v.trim().toLowerCase());
        
        const selectedValueLower = String(selectedValue || '').toLowerCase();
        
        if (unsuitableValues.includes(selectedValueLower)) {
            feedbackDiv.style.display = 'block';
        } else {
            feedbackDiv.style.display = 'none';
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

})();
___BEGIN___COMMAND_DONE_MARKER___0
