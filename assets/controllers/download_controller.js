import { Controller } from '@hotwired/stimulus';
import template       from 'lodash/template';
import { Centrifuge } from 'centrifuge';

export default class extends Controller {

    static targets = ['error', 'list', 'resourceTemplate', 'alertTemplate'];

    downloads = [];

    static values = {
        apiUrl: String,
        websocketUrl: String,
        token: String,
    };

    centrifuge= null;
    sub       = null;

    connect() {
        console.log('connect');

        this.centrifuge = new Centrifuge(`ws://${this.websocketUrlValue}/connection/websocket`, {
            token: this.tokenValue
        });

        this.centrifuge.on('connect', function(connectCtx){
            console.log('connected', connectCtx)
        });

        this.centrifuge.on('disconnect', function(disconnectCtx){
            console.log('disconnected', disconnectCtx)
        });

        this.sub = this.centrifuge.newSubscription('downloads');

        this.sub.on('publication', (function(ctx) {
            this.addData(ctx.data);
        }).bind(this));

        // Trigger subscribe process.
        this.sub.subscribe();

        // Trigger actual connection establishement.
        this.centrifuge.connect();

        setInterval( (function() {
            this.downloads = this.downloads.filter(item => item.percentage !== 100);
            this.renderResults();
            self.errorTarget.innerHTML = "";
        }).bind(this), 10000);
    }

    disconnect() {
        this.centrifuge.disconnect();

        console.log('Disconnected from channel downloads');
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
