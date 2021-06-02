import { Controller } from 'stimulus';
import { template } from 'lodash';
import Centrifuge from 'centrifuge';

export default class extends Controller {

    static targets = ['list', 'resourceTemplate', 'alertTemplate'];

    downloads = [];

    static values = {
        apiUrl: String,
        websocketUrl: String,
        token: String,
    };

    centrifuge = null;

    connect() {
        this.centrifuge = new Centrifuge(`ws://${this.websocketUrlValue}/connection/websocket`);

        this.centrifuge.setToken(this.tokenValue);

        this.centrifuge.subscribe("downloads", (function(ctx) {
            this.addData(ctx.data);
        }).bind(this));

        this.centrifuge.connect();

        setInterval( (function() {
            this.downloads = this.downloads.filter(item => item.percentage !== 100);
            this.renderResults();
        }).bind(this), 10000);
    }

    disconnect() {
        this.centrifuge.disconnect();
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
