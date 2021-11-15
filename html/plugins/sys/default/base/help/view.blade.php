<!DOCTYPE HTML>
@php
    list($borwserName, $borwser_ext) = plu('sys.default')->call('tools:getBrowser');
    $isIe = $borwserName === 'IE';
    $isFooter = !empty($footer);
    if ($urlRootBase == '/') {
        $urlSeo = "";
    } else {
        $urlSeo = $urlRootBase;
    }

    $isDebug = \Tphp\Config::$domain['debug'];
    if (!is_bool($isDebug)) {
        $isDebug = false;
    }

    $plu->css([
        'js/vue/element/index.css',
        'js/plugins/help/css/index.css',
        'js/plugins/help/css/github.css',
        'js/plugins/help/photoswipe/photoswipe.css',
        'js/plugins/help/photoswipe/default-skin/default-skin.css'
    ]);

    if ($isIe) {
        $plu->js('@js/ie/polyfill.min.js');
    } else {
        $plu->css(
            'js/plugins/help/highlight/xcode.css'
        )->js(
            '@js/plugins/help/highlight/highlight.js'
        );
    }

    $plu->js([
        '@js/vue/vue.min.js',
        '@js/vue/axios.min.js',
        '@js/plugins/help/js/marked.min.js',
        '@js/vue/element/index.js',
        '@js/plugins/help/js/clipboard.min.js',
        '@js/plugins/help/photoswipe/photoswipe.min.js',
        '@js/plugins/help/photoswipe/photoswipe-ui-default.min.js',
        'js/plugins/help/js/index.js'
    ]);

    if (!empty($mainTitle)) {
        if (empty($title)) {
            $title .= $mainTitle;
        } else {
            $title .= " - {$mainTitle}";
        }
    }
@endphp
<html>
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="viewport-fit=cover, width=device-width, height=device-height, initial-scale=1, minimum-scale=1, maximum-scale=1, user-scalable=no" />
    <title>{{$title}}</title>
    <meta name="keywords" content="{{$keywords}}"/>
    <meta name="description" content="{{$description}}"/>
    {!! $head !!}
</head>
<body>
<div class="pswp" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="pswp__bg"></div>
    <div class="pswp__scroll-wrap">
        <div class="pswp__container">
            <div class="pswp__item"></div>
            <div class="pswp__item"></div>
            <div class="pswp__item"></div>
        </div>
        <div class="pswp__ui pswp__ui--hidden">
            <div class="pswp__top-bar">
                <div class="pswp__counter"></div>
                <button class="pswp__button pswp__button--close" title="Close (Esc)"></button>
                <button class="pswp__button pswp__button--fs" title="Toggle fullscreen"></button>
                <button class="pswp__button pswp__button--zoom" title="Zoom in/out"></button>
                <div class="pswp__preloader">
                    <div class="pswp__preloader__icn">
                        <div class="pswp__preloader__cut">
                            <div class="pswp__preloader__donut"></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="pswp__caption">
                <div class="pswp__caption__center"></div>
            </div>
        </div>
    </div>
</div>
<div id="app" style="display: none" v-show="true">
    <el-backtop :right="30" :bottom="20"><i class="el-icon-arrow-up"></i></el-backtop>
    <transition name="sidebar">
        <div class="sidebar" :class="is_search_action ? 'css_is_search' : ''" v-show="menu_show">
            <el-container>
                <el-header class="sidebar-header">
                    <a href="{{$urlRootBase}}" @click.prevent="to_default">{{$mainTitle}}</a>
                </el-header>
                <el-header class="sidebar-header search">
                    <el-input size="small" placeholder="搜索关键字" v-model="search" @keydown.enter.native="search_enter" ref="search">
                        <template slot="append">
                        <span class="el-input__suffix search_clear" style="transform: translateX(-36px);" @click="search_clear" v-show="is_search()">
                            <span class="el-input__suffix-inner"><i class="el-input__icon el-icon-close"></i></span>
                        </span>
                        </template>
                        <el-button slot="append" icon="el-icon-search" @click="search_enter"></el-button>
                    </el-input>
                </el-header>
                <el-main>
                    <el-tree
                            :data="data" node-key="id" :default-expanded-keys="expanded"
                            :current-node-key="node_key" :props="defaultProps" :expand-on-click-node="false"
                            @node-click="read" highlight-current ref="tree"
                            class="sidebar-tree @if($isFooter) sidebar-tree-footer @endif"
                    >
                        @if(!$isIe)<a :href="node.data.id" slot-scope="{ node }" @click.prevent="">
                            @{{ node.label }}
                        </a>@endif
                    </el-tree>
                    @if($isFooter) <div class="footer">{!! $footer !!}</div> @endif
                </el-main>
            </el-container>
        </div>
    </transition>
    <div :class="menu_show ? 'body-menu' : 'body'">
        <div class="sidebar-show" @click="menu_show = !menu_show"><i class="el-icon-menu"></i></div>
        <div class="content_header"><h1>@{{ menu_name }}</h1></div>
        <div class="content_body">
            <div v-html="content_html" class="markdown"></div>
            <div class="content_body_jump">
                <div class="jump jump_left" v-show="prev_info !== false">上一篇：<a :href="prev_info.id" @click.prevent="jump(prev_info.id)">@{{ prev_info.title }}</a></div>
                <div class="jump jump_right" v-show="next_info !== false">下一篇：<a :href="next_info.id" @click.prevent="jump(next_info.id)">@{{ next_info.title }}</a></div>
            </div>
        </div>
        <div class="content" v-show="false" ref="content">{{ $content }}</div>
    </div>
</div>
<div class="seo">
@foreach($indexs as $index)
    <a href="{{$urlSeo}}/{{$index}}">{{$paths[$index]['title']}}</a>
@endforeach
@foreach($seoList as $sl)
    <a href="{{$sl}}">{{$sl}}</a>
@endforeach
</div>
<script>
    var config = '{!! json_encode($config) !!}';
    var id = '{{$id}}';
    var ids = {!! json__encode($indexs) !!};
    var url_root = '{{$urlRoot}}';
    var url_root_base = '{{$urlRootBase}}';
    var is_top = {{$isTop ? 'true' : 'false'}};
    var is_debug = {{$isDebug ? 'true' : 'false'}};
    var is_ie = {{$isIe ? 'true' : 'false'}};
    var main_title = '{{$mainTitle}}';
</script>
{!! $body !!}
</body>
</html>
