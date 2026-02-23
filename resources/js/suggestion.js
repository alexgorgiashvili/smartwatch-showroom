/**
 * AI Suggestion Handler
 *
 * Handles AI-powered suggestion generation and display for customer support replies
 */

class AiSuggestionHandler {
    constructor(conversationId, options = {}) {
        this.conversationId = conversationId;
        this.options = {
            endpoint: `/admin/inbox/${conversationId}/suggest-ai`,
            maxSuggestions: 3,
            ...options
        };
        this.suggestions = [];
        this.isLoading = false;
    }

    /**
     * Fetch suggestions from the server
     * @returns {Promise<Array|null>} Array of suggestions or null if failed
     */
    async getSuggestions() {
        if (this.isLoading) {
            return null;
        }

        this.isLoading = true;

        try {
            const response = await fetch(this.options.endpoint, {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': this._getCSRFToken(),
                },
            });

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const data = await response.json();

            if (data.success && data.suggestions && Array.isArray(data.suggestions)) {
                this.suggestions = data.suggestions;
                return this.suggestions;
            }

            throw new Error(data.message || 'Failed to generate suggestions');
        } catch (error) {
            console.error('Error fetching suggestions:', error);
            return null;
        } finally {
            this.isLoading = false;
        }
    }

    /**
     * Display suggestions in a container
     * @param {HTMLElement} container - Container element to display suggestions in
     * @param {Array<string>} suggestions - Array of suggestion strings
     * @param {Function} onSelect - Callback when a suggestion is selected
     */
    displaySuggestions(container, suggestions, onSelect) {
        if (!container || !suggestions || suggestions.length === 0) {
            return;
        }

        container.innerHTML = '';

        suggestions.forEach((suggestion, index) => {
            const pill = document.createElement('button');
            pill.type = 'button';
            pill.className = 'btn btn-sm btn-outline-primary suggestion-pill';
            pill.setAttribute('data-index', index);
            pill.title = suggestion;

            const displayText = suggestion.length > 60
                ? suggestion.substring(0, 60) + '...'
                : suggestion;

            pill.innerHTML = `<small>${this._escapeHtml(displayText)}</small>`;

            pill.addEventListener('click', (e) => {
                e.preventDefault();
                if (onSelect) {
                    onSelect(suggestion, index);
                }
            });

            container.appendChild(pill);
        });
    }

    /**
     * Insert suggestion into a textarea
     * @param {HTMLTextAreaElement} textarea - Textarea element
     * @param {string} text - Text to insert
     * @param {Function} onCharCountChange - Callback when character count changes
     */
    insertSuggestion(textarea, text, onCharCountChange) {
        if (!textarea) {
            return;
        }

        textarea.value = text;
        textarea.focus();

        if (onCharCountChange) {
            onCharCountChange(text.length);
        }
    }

    /**
     * Clear all suggestions
     * @param {HTMLElement} container - Container to clear
     */
    clearSuggestions(container) {
        if (container) {
            container.innerHTML = '';
        }
        this.suggestions = [];
    }

    /**
     * Get CSRF token from meta tag
     * @returns {string} CSRF token
     */
    _getCSRFToken() {
        return document.querySelector('meta[name="csrf-token"]')?.content || '';
    }

    /**
     * Escape HTML special characters
     * @param {string} text - Text to escape
     * @returns {string} Escaped text
     */
    _escapeHtml(text) {
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.replace(/[&<>"']/g, m => map[m]);
    }
}

/**
 * Batch generate suggestions for multiple conversations
 * @param {Array<number>} conversationIds - Array of conversation IDs
 * @returns {Promise<Object|null>} Results object or null if failed
 */
async function batchGenerateSuggestions(conversationIds) {
    if (!Array.isArray(conversationIds) || conversationIds.length === 0) {
        console.error('Invalid conversation IDs');
        return null;
    }

    try {
        const response = await fetch('/admin/inbox/suggestions/batch', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
            },
            body: JSON.stringify({
                conversation_ids: conversationIds.slice(0, 10), // Max 10 per batch
            }),
        });

        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        const data = await response.json();

        if (data.success) {
            return data;
        }

        throw new Error(data.message || 'Batch generation failed');
    } catch (error) {
        console.error('Error in batch generation:', error);
        return null;
    }
}

// Export for use in modules
if (typeof module !== 'undefined' && module.exports) {
    module.exports = {
        AiSuggestionHandler,
        batchGenerateSuggestions,
    };
}
