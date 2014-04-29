<div id="home_page" class='body-wrapper'>
	<div id="main_logo">
		<img src="<?php echo url_for("/images/main-logo.png"); ?>" />
	</div>

	<div id="get-it">
		<span id="tipi-version"><?php echo Tipi::getDisplayVersion(); ?> [<em>PDF</em>, <em>CHM</em>, <em>MOBI</em>, <em>ePub</em>]</span>
		<a class='read' href='<?php echo url_for("/book/"); ?>'><span>阅读</span></a>
		<span id="get-it-sep"></span>
		<a class='downloads' href='<?php echo url_for("/downloads/"); ?>'><span>下载</span></a>
	</div>

	<div id="get-started" class="clearfix">
		<div class='get-it'>
			<div class="inner">
				<h2>获取<span>Get/Git it</span></h2>
				<div>
					TIPI是一个开源项目，项目包含的内容有：
					<ul>
						<li>《深入理解PHP内核》</li>
						<li>本站所有的PHP源代码</li>
						<li>本项目相关的一些项目源代码</li>
						<li>本站设计和使用的一些素材</li>
					</ul>
					<div>
					<p>
						我们的项目托管在github上:
						<div id="github-link"><a href="http://github.com/reeze/tipi" target='_blank'>http://github.com/reeze/tipi</a></div>
						<p>
							欢迎fork, 如果只想下载《深入理解PHP内核》这本书，请点击页面右上部分的下载链接下载，
							项目目前并没有完成所有的内容。
						</p>
						</div>
					</p>
				</div>
			</div>
		</div>
		<div class='enjoy-it'>
			<div class="inner">
				<h2>使用<span>Enjoy It</span></h2>
				<p>
					我们提供《深入理解PHP内核》的在线阅读及PDF、CHM、ePub、Mobie格式的下载，如果你网络环境不好，想离线浏览，您也可以下载我们的整个项目。请参考<a href="<?php echo url_for("/about/"); ?>">关于页面</a>，这里有本地使用的一些说明。
				</p>
				<p>
					虽然TIPI项目主要关注PHP内部实现和相关技术的研究，作为互联网爱好者，无论是互联网产品还是电子书籍，
					我们都希望能有良好的使用及阅读体验，我们也会将精力投入到用户体验上，尽可能为大家提供一个更好的使用感受。
				</p>
			</div>
		</div>
		<div class='keep-in-touch'>
			<div class="inner">
				<h2>保持更新<span>Keep In Touch</span></h2>
				<div>
				<!--
					<div id="lastest-news">现已经提供PDF版本的下载, <a href="<?php echo url_for("/news/?p=2011-04-01-add-sections-typo-fix-and-improvement"); ?>">点击浏览详情</a></div>
					-->
					<div id="microblog" class="clearfix">
							<a class='sina t' target='_blank' href="http://weibo.com/tipiteam"><span>新浪微博</span></a>
							<a class='twitter t' target='_blank' href="http://twitter.com/teamtipi"><span>Twitter</span></a>
							<a class='rss t' target='_blank' href="<?php echo url_for("/feed/"); ?>"><span>订阅</span></a>
					</div>
					<p>
					想要和我们保持联系很简单，可以通过上面的微博和我们联系，也可以订阅我们的更新。
					</p>
					<p>
					如果你喜欢使用RSS阅读器的话那最好不过了。点击上面的订阅图标订阅吧。
					当然，我们的RSS输出是全文的(我们恨透了非全文输出)。我们每写完一小节都会通过RSS输出，同时一些相关的更新信息也会通过这个源输出。
					</p>
				</div>
			</div>
		</div>
	</div>
</div>
