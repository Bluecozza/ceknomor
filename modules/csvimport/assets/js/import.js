// CSV Import Plugin JavaScript
// Helper functions used in admin page

class CSVImporter {
    constructor(apiUrl, token) {
        this.apiUrl = apiUrl;
        this.token = token;
        this.sessionId = null;
    }

    setSessionId(sessionId) {
        this.sessionId = sessionId;
    }

    async upload(file) {
        const formData = new FormData();
        formData.append('file', file);

        const response = await fetch(this.apiUrl + '/upload', {
            method: 'POST',
            headers: {
                'Authorization': `Bearer ${this.token}`
            },
            body: formData
        });

        return await response.json();
    }

    async getPreview(sessionId) {
        const response = await fetch(`${this.apiUrl}/preview/${sessionId}`, {
            headers: {
                'Authorization': `Bearer ${this.token}`
            }
        });

        return await response.json();
    }

    async updateRecord(sessionId, lineNo, status) {
        const response = await fetch(
            `${this.apiUrl}/record/${sessionId}/${lineNo}`,
            {
                method: 'PUT',
                headers: {
                    'Authorization': `Bearer ${this.token}`,
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ status })
            }
        );

        return await response.json();
    }

    async bulkAction(sessionId, action) {
        const response = await fetch(
            `${this.apiUrl}/bulk-action/${sessionId}`,
            {
                method: 'POST',
                headers: {
                    'Authorization': `Bearer ${this.token}`,
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ action })
            }
        );

        return await response.json();
    }

    async submit(sessionId) {
        const response = await fetch(
            `${this.apiUrl}/submit/${sessionId}`,
            {
                method: 'POST',
                headers: {
                    'Authorization': `Bearer ${this.token}`,
                    'Content-Type': 'application/json'
                }
            }
        );

        return await response.json();
    }
}