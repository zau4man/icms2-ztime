const ZtimeItem = {
    props: ['newsitem'],
    methods: {
        showItem(){
            this.$emit('showitem',this.newsitem.id);
        }
    },
    computed: {
        bg(){
            return this.newsitem.image ? 'background-image:url(/upload/'+this.newsitem.image+')' : null;
        },
        id(){
            return 'zitem_'+this.newsitem.id;
        }
    },
    template: `
<div class="ztime__item zitem shadow p-4 mb-4" :id="id" :key="newsitem.id" @click="showItem">
    <div class="zitem__info">
        <div class="zitem__type mr-3">{{ newsitem.type }}</div>
        <div class="zitem__date mr-3">{{ newsitem.date }}</div>
    </div>
    <div class="zitem__title">
        <div class="zitem__photo mr-3" v-if="bg" :style="bg"></div>
        <div class="zitem__titletext">{{ newsitem.title }}</div>
    </div>
    <div class="zitem__content mt-4" v-if="newsitem.content" v-html="newsitem.content"></div>
</div>
`
};
const ZtimeItems = {
    components: {
        ZtimeItem
    },
    methods: {
        showItem(id){
            this.$emit('showitem',id);
        }
    },
    props: ['news'],
    template: `<div class="ztime__items"><transition-group name="ztimeitems"><ZtimeItem v-for="newsitem in news"  :key="newsitem.id" :newsitem="newsitem" @showitem="showItem"></ZtimeItem></transition-group></div>`
};
const ztime = Vue.createApp({
    components: {
        ZtimeItems
    },
    data(){
        return {
            news: [],
            page: 0,
            isLoading: false,
            isEnded: false,
            active: false,
            href: 'ztime'
        }
    },
    methods: {
        async getNews(){
            try {
                this.page += 1;
                this.isLoading = true;
                const response = await fetch(this.href+'/content/list/'+this.page);
                let data = await response.json();
                if(data.length > 0){
                    this.news = [...this.news, ...data];
                }else{
                    this.isEnded = true;
                }
            } catch (e) {
                console.log(e);
            } finally {
                this.isLoading = false;
            }
        },
        async showItem(id){

            let index = this.news.findIndex(item => item.id === id);

            if((this.active === id) && this.news[index].content){
                delete(this.news[index].content);
                return;
            }

            try {
                const response = await fetch(this.href+'/content/item/'+id);
                let newsitem = await response.json();

                //если совпадение с active id то тут удалим
                //а ниже при совпадении ниче не делаем

                this.news[index] = newsitem;
                if(this.active !== id){
                    await this.changeActive(id);
                }

                this.scrollTo(id);
            } catch (e) {
                console.log(e);
            }
        },
        scrollTo(id){
            document.getElementById('zitem_'+id).scrollIntoView({behavior: 'smooth'});
        },
        changeActive(id){
            if(this.active){
                let index = this.news.findIndex(item => item.id === this.active);
                delete(this.news[index].content);
            }
            this.active = id;
        },
        more(){
            const options = {
                rootMargin: '100px',
                threshold: 1.0
            };
            const callback = (entries, observer) => {
                if(entries[0].isIntersecting){
                    if(!this.isEnded && !this.isLoading){
                        this.getNews();
                    }
                }
            };
            const observer = new IntersectionObserver(callback, options);
            observer.observe(this.$refs.morenews);
        }
    },
    beforeMount(){
        this.href = document.getElementById('ztime').getAttribute('data-href');
    },
    mounted(){
        this.getNews();
        this.more();
    },
    template: `
<div>
    <ZtimeItems :news="news" @showitem="showItem"></ZtimeItems>
    <div ref="morenews" class="morenews"></div>
    <div class="ztime__ended shadow p-4" v-if="isEnded">Показаны все записи</div>
</div>
    `
}).mount('#ztime');