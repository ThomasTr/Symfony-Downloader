import { Controller } from 'stimulus';
import { template } from "lodash"

export default class extends Controller {

    static targets = ['list', 'resourceTemplate', 'alertTemplate'];

    downloads = [];

    static values = {
        apiUrl: String,
        mercureUrl: String
    };

    connect() {
        var self = this;
        const eventSource = new EventSource(this.mercureUrlValue);
        eventSource.onmessage = function(message) {
            const data = JSON.parse(message.data);

            self.addData(data);
        }
    }

    disconnect() {
        this.eventSource.close();
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
                self.listTarget.innerHTML += alertTemplate(download);
            }
            else
            {
                // const resourceTemplate = template(self.resourceTemplateTarget.innerHTML);
                // self.listTarget.innerHTML += resourceTemplate(download);

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
