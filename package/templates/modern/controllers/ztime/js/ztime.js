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
        },
        clink(){
            return this.newsitem.link + '#comments'
        }
    },
    template: `
<div class="ztime__item zitem shadow p-4 mb-4" :id="id" :key="newsitem.id">
    <div class="ztime__body" @click="showItem">
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
    <div class="zitem__bar my-2" v-if="newsitem.link">
    <a class="zitem__comments btn btn-light" :href="clink" v-if="newsitem.comments" target="_blank"><svg class="icms-svg-icon w-16" fill="currentColor"><use href="/templates/modern/images/icons/solid.svg#comments"></use></svg><span>{{ newsitem.comments }}</span></a><a class="zitem__link btn btn-light" :href="newsitem.link" v-if="newsitem.link" target="_blank"><span>перейти</span><svg class="icms-svg-icon w-16" fill="currentColor"><use href="/templates/modern/images/icons/solid.svg#arrow-right"></use></svg></a>
    </div>
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
const ZtimeTypes = {
    props: ['types'],
    methods: {
        changeType(id){
            this.$emit('changetype',id);
        }
    },
    template: `<ul class="ztime__types nav nav-pills pb-3"><li class="nav-item" v-for="type in types" :key="type.id"><a class="nav-link" :class="{active: type.active}" @click.stop="changeType(type.id)" href="#">{{ type.title }}</a></li></ul>`
};
const ZtimeSearch = {
    data(){
        return {
            search : ''
        }
    },
    watch: {
        search(val){
            if(this.timer){
                clearTimeout(this.timer);
            }
            this.timer = setTimeout(() => {
                this.$emit('searchit',val);
            }, 500);

        }
    },
    template: `<div class="ztime__search pb-3"><input v-model="search" type="search" class="form-control" placeholder="Начните вводить название..."></div>`
};
document.addEventListener("DOMContentLoaded", () => {
    const ztime = Vue.createApp({
        components: {
            ZtimeItems, ZtimeTypes, ZtimeSearch
        },
        data() {
            return {
                news: [],
                types: [],
                page: 0,
                isLoading: false,
                isEnded: false,
                active: false,
                href: 'ztime',
                search: ''
            };
        },
        methods: {
            async getNews() {
                try {
                    this.page += 1;
                    this.isLoading = true;
                    let fdata = new FormData();
                    fdata.append('types', this.getActiveTypesStr());
                    fdata.append('search', this.search);
                    const response = await fetch(this.href + '/content/list/' + this.page, {
                        method: 'POST',
                        body: fdata
                    });
                    let data = await response.json();
                    if (data.length > 0) {
                        this.news = [...this.news, ...data];
                    } else {
                        this.isEnded = true;
                    }
                } catch (e) {
                    console.log(e);
                } finally {
                    this.isLoading = false;
                    this.checkView();
                }
            },
            checkView() {
                if (!this.isEnded) {
                    if (document.querySelector('.ztime__items').clientHeight < document.documentElement.clientHeight) {
                        this.getNews();
                    }
                }
            },
            getActiveTypesStr() {
                return this.types.filter(item => item.active === 1).map(function (item) {
                    return item.name;
                }).join(',');
            },
            searchIt(text) {
                this.search = text;
                this.reload();
            },
            async getTypes() {
                try {
                    const response = await fetch(this.href + '/types');
                    let data = await response.json();
                    if (data.length > 0) {
                        this.types = data;
                    }
                } catch (e) {
                    console.log(e);
                }
            },
            async showItem(id) {

                let index = this.news.findIndex(item => item.id === id);

                if ((this.active === id) && this.news[index].content) {
                    this.hideActive(index);
                    return;
                }

                try {
                    const response = await fetch(this.href + '/content/item/' + id);
                    let newsitem = await response.json();

                    this.news[index] = newsitem;
                    if (this.active !== id) {
                        await this.changeActive(id);
                    }

                    this.scrollTo(id);
                } catch (e) {
                    console.log(e);
                }
            },
            scrollTo(id) {
                document.getElementById('zitem_' + id).scrollIntoView({behavior: 'smooth'});
            },
            hideActive(index) {
                delete(this.news[index].content);
                delete(this.news[index].link);
                delete(this.news[index].comments);
            },
            changeActive(id) {
                if (this.active) {
                    let index = this.news.findIndex(item => item.id === this.active);
                    this.hideActive(index);
                }
                this.active = id;
            },
            changeType(id) {
                let index = this.types.findIndex(item => item.id === id);
                if ((this.types.filter(item => item.active === 1).length <= 1) && (this.types[index].active === 1)) {
                    return false;
                }
                this.types[index].active = +!this.types[index].active;
                this.reload();
            },
            reload() {
                this.page = 0;
                this.isEnded = false;
                this.news = [];
                this.getNews();
            },
            more() {
                const options = {
                    rootMargin: '100px',
                    threshold: 1.0
                };
                const callback = (entries, observer) => {
                    if (entries[0].isIntersecting) {
                        if (!this.isEnded && !this.isLoading) {
                            this.getNews();
                        }
                    }
                };
                const observer = new IntersectionObserver(callback, options);
                observer.observe(this.$refs.morenews);
            }
        },
        beforeMount() {
            this.href = document.getElementById('ztime').getAttribute('data-href');
        },
        async mounted() {
            await this.getTypes();
            await this.getNews();
            this.more();
        },
        template: `
<div>
    <ZtimeTypes :types="types" v-if="types" @changetype="changeType"></ZtimeTypes>
    <ZtimeSearch @searchit="searchIt"/>
    <ZtimeItems :news="news" @showitem="showItem"></ZtimeItems>
    <div ref="morenews" class="morenews"></div>
    <div class="ztime__ended shadow p-4" v-if="isEnded"><span v-if="search">Больше ничего не найдено...</span><span v-else>Показаны все записи...</span></div>
</div>
    `
    }).mount('#ztime');
});