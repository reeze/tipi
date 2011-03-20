<div id="home_page" class='body-wrapper'>
	<div id="main_logo">
		<img src="images/main-logo.png" />
	</div>

	<div id="get-it">
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
						<div id="github-link"><a href="http://github.com/reeze/tipi">http://github.com/reeze/tipi</a></div>
						<p>
							欢迎fork, 如果只想下载《深入理解PHP内核》这本书，请点击页面右上部分的下载链接下载，不过项目还处在第一阶段，
							并没有完成所有的内容.
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
					我们提供《深入理解PHP内核》的在线阅读，后续也会提供比如:pdf,epub,chm等格式，这是在我们的计划当中的。
					如果你网络环境不好，或者希望离线阅读，目前您可以下载我们的整个项目. 请参考<a href="<?php url_for("/about/"); ?>">关于页面</a>,这里有本地使用的一些说明。
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
					<div id="microblog" class="clearfix">
							<a class='sina t' href="http://t.sina.com.cn/tipiteam"><span>新浪微博</span></a>
							<a class='twitter t' href="http://twitter.com/teamtipi"><span>Twitter</span></a>
							<a class='rss t' href="<?php echo url_for("/feed/"); ?>"><span>订阅</span></a>
					</div>
					<p>
					想要和我们保持联系很简单，有如下的方式可以使用	
					</p>
					<p>
					如果你喜欢使用类似GReader之类RSS阅读器的话，那这最好不过了，可以通过订阅如下的地址：

					当然，我们的RSS输出是全文的(我们恨透了非全文输出)。我们每写完一小节都会通过RSS输出，同时一些相关的信息也可以通过这里获得。
					</p>
				</div>
			</div>
		</div>
	</div>
</div>
