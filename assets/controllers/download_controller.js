import { Controller } from '@hotwired/stimulus';
import template       from 'lodash/template';

export default class extends Controller {

    static targets = ['error', 'list', 'resourceTemplate', 'alertTemplate'];

    downloads = [];

    static values = {
        apiUrl: String,
        mercureUrl: String,
    };

    connect() {
        console.log('connect');

        const eventSource = new EventSource(this.mercureUrlValue);
        eventSource.onmessage = event => {
            // Will be called every time an update is published by the server
            console.log(JSON.parse(event.data));

            this.addData(JSON.parse(event.data));
        }

        setInterval( (function() {
            this.downloads = this.downloads.filter(item => item.percentage !== 100);
            this.renderResults();
            self.errorTarget.innerHTML = "";
        }).bind(this), 10000);
    }

    disconnect() {
    }

    addData(data) {
        if(undefined === this.downloads.find(download => download.id === data.id))
        {
            this.downloads.push(data);
        }
        else
        {
            const index = this.downloads.findIndex((download => download.id === data.id));
            this.downloads[index] = data;
        }

        this.renderResults()
    }

    renderResults() {
        this.listTarget.innerHTML = '';
        self = this;
        this.downloads.forEach(function (download) {
            if(null !== download.alertMessage)
            {
                const alertTemplate = template(self.alertTemplateTarget.innerHTML);

                self.errorTarget.innerHTML = alertTemplate(download);
            }
            else
            {
                var commentTemplate = document.getElementById("resource-template").innerHTML;
                var templateFn = template(commentTemplate);
                self.listTarget.innerHTML += templateFn(download);
            }
        });
    }

    async download() {
        const url = document.getElementById('url');
        const params = new URLSearchParams({
            url: encodeURIComponent(url.value)
        });

        url.value = '';

        await fetch(`${this.apiUrlValue}?${params.toString()}`);
    }
}
