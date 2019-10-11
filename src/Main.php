<?php 
include 'SpellCorrector.php';
include 'simple_html_dom.php';

header('Content-Type: text/html; charset=utf-8');
ini_set('memory_limit',-1);
ini_set('max_execution_time', 300);
$correct_query="";
$limit = 10;
$flag = 0;
$query = isset($_REQUEST['q']) ? $_REQUEST['q'] : false;
$results = false;
if ($query)
{

  require_once('/var/www/html/solr-php-client-master/Apache/Solr/Service.php');
  $solr = new Apache_Solr_Service('localhost', 8983, '/solr/myexample');
  $query_terms = explode(" ", $query);
  if (get_magic_quotes_gpc() == 1)
  {
    $query = stripslashes($query);
  }
  for($i = 0 ; $i < sizeof($query_terms); $i++)
  {     
  	$chk = SpellCorrector::correct($query_terms[$i]);
      
  	if($i == 0)
  		$correct_query = $correct_query . $chk;
  	else
  		$correct_query = $correct_query .' '. $chk;
  }
  if(strtolower($query) != strtolower($correct_query))
  {
  	$flag = 1;
  }
  try
  {
  	if($_GET['algo'] == "lucene")//by default - lucene
  	{
    		$additionalParameters = array('sort' => 'pageRankFile desc');
  		  	$results = $solr->search($query, 0, $limit, $additionalParameters);
  	}
  	else
  	{

			$results = $solr->search($query, 0, $limit);
  	}
  }
  catch (Exception $e)
  {
    die("<html><head><title>SEARCH EXCEPTION</title><body><pre>{$e->__toString()}</pre></body></html>");
  }
}
?>

<html>
  <head>
    <title>IR-Assignment 5</title>
    <link href="http://code.jquery.com/ui/1.10.4/themes/ui-lightness/jquery-ui.css" rel="stylesheet"></link>
	<script src="http://code.jquery.com/jquery-1.10.2.js"></script>
	<script src="http://code.jquery.com/ui/1.11.4/jquery-ui.js"></script>
  </head>
  <style>
	body{
		background-color: white;
              
	}
   </style>	
  <body>
      <h1 align="center"> Solr Client Application</h1><br/>
      <form accept-charset="utf-8" method="get" align="center">
      <label for="q">Search : </label>
      <input id="q" name="q" type="text" value="<?php echo htmlspecialchars($query, ENT_QUOTES, 'utf-8'); ?>"/>
      <br/><br/>
      <input type="radio" name="algo" value="lucene"<?php if(isset($_REQUEST['algo']) && $_REQUEST['algo'] == 'lucene') {echo 'checked="checked"';} ?>> Lucene
      <input type="radio" name="algo" value="pagerank"<?php if(isset($_REQUEST['algo']) && $_REQUEST['algo'] == 'pagerank') {echo 'checked="checked"';} ?>> Page Rank
      <br/>
      <br/>
      <input type="submit"/>
    </form>
    <script>
	$(function() {
		var URL_PREFIX = "http://localhost:8983/solr/myexample/suggest?q=";
		var URL_SUFFIX = "&wt=json";
		var final_suggest = [];
		var previous= "";
		$("#q").autocomplete({
			source : function(request, response) {
				previous = "";
				var q = $("#q").val().toLowerCase();
         		var sp =  q.lastIndexOf(' ');
         		if(q.length - 1 > sp && sp != -1)
         		{
          			final_query = q.substr(sp+1);
          			previous = q.substr(0,sp);
        		}
        		else
        		{
          			final_query = q.substr(0); 
        		}
				var URL = URL_PREFIX + final_query + URL_SUFFIX;
				$.ajax({
					url : URL,
					success : function(data) {
							  var docs = JSON.stringify(data.suggest.suggest);
							  var jsonData = JSON.parse(docs);
							  var result =jsonData[final_query].suggestions;
							  var j=0;
							  var suggest = [];
							  for(var i=0 ; i<5 && j<result.length ; i++,j++){
									if(final_query == result[j].term)
									{
								  		--i;
								  		continue;
									}
									for(var l=0;l<i && i>0;l++)
									{
									  	if(final_suggest[l].indexOf(result[j].term) >=0)
									  	{
											--i;
									  	}
									}
									if(suggest.length == 5)
									  break;
									if(suggest.indexOf(result[j].term) < 0)
									{
									  suggest.push(result[j].term);
									  if(previous == ""){
										final_suggest[i]=result[j].term;
									  }
									  else
									  {
										final_suggest[i] = previous + " ";
										final_suggest[i]+=result[j].term;
									  }
									}
							  }
							  response(final_suggest);
					},
					close: function () {
         				this.value='';
    					},
					dataType : 'jsonp',
 					jsonp : 'json.wrf'
 				});
 				},
 			minLength : 1
 			})
 		});
</script>
<?php
if ($results)
{
  $total = (int) $results->response->numFound;
  $start = min(1, $total);
  $end = min($limit, $total);
  if($flag == 1){
	echo "Showing results for ", ucwords($query);
	$link = "http://localhost/Main.php?q=$correct_query&algo=".$_REQUEST['algo'];
	echo "<br>Search instead for <a href='$link'>$correct_query</a>";
}
?>
    <div>Results <?php echo $start; ?> - <?php echo $end;?> of <?php echo $total; ?>:</div>
    <ol>
<?php
  $csv = array_map('str_getcsv', file('/var/www/html/URLtoHTML_guardian_news.csv'));
  foreach ($results->response->docs as $doc)
  {     echo '<div>';
	
	$id = $doc->id;
  	$title = $doc->og_title;
  	$url = $doc->og_url;
  	$desc = $doc->og_description;
  	if($desc == "" || $desc == null)
  	{
  		$desc = "N/A";
	}
	if($title == "" || $title == null)
  	{
  		$title = "N/A";
	}
	if($url == "" || $url == null)
	{
	foreach($csv as $row)
		{
			$cmp = "/home/karishma/shared/crawl_data/guardian/guardiannews/" + $row[0];
			if($id == $cmp)
			{
				$url = $row[1];
				unset($row);
				break;
			}
		}
	}
	$textsnip = "";
	$query_terms = explode(" ", $query);
	$count = 0;
	$max = sizeof($query_terms);
	$prev_max = 0;
	$file_content = file_get_contents($id);
	 $html = str_get_html($file_content);
	// $content

	$html = strip_tags($file_content);

		$text = preg_replace(
			array(
				'@<head[^>]*?>.*?</head>@siu',
				'@<style[^>]*?>.*?</style>@siu',
				'@<script[^>]*?.*?</script>@siu',
				'@<object[^>]*?.*?</object>@siu',
				'@<embed[^>]*?.*?</embed>@siu',
				'@<applet[^>]*?.*?</applet>@siu',
				'@<noframes[^>]*?.*?</noframes>@siu',
				'@<noscript[^>]*?.*?</noscript>@siu',
				'@<noembed[^>]*?.*?</noembed>@siu',

				'@<((br)|(hr))@iu',
				'@</?((address)|(blockquote)|(center)|(del))@iu',
				'@</?((div)|(h[1-9])|(ins)|(isindex)|(p)|(pre))@iu',
				'@</?((dir)|(dl)|(dt)|(dd)|(li)|(menu)|(ol)|(ul))@iu',
				'@</?((table)|(th)|(td)|(caption))@iu',
				'@</?((form)|(button)|(fieldset)|(legend)|(input))@iu',
				'@</?((label)|(select)|(optgroup)|(option)|(textarea))@iu',
				'@</?((frameset)|(frame)|(iframe))@iu',
			),
			array(
				' ', ' ', ' ', ' ', ' ', ' ', ' ', ' ', ' ',
				"\n\$0", "\n\$0", "\n\$0", "\n\$0", "\n\$0", "\n\$0",
				"\n\$0", "\n\$0",
			),
			$file_content );

		$content =  strip_tags( $text );
   
  		$sent = strtolower($content);
  		if(strpos($sent, strtolower($query)) != 0)
  		{
  			$textsnip = $sent;
  			$pos_term = 0;
		  	$start_pos = 0;
		  	$end_pos = 0;
			$pos_term = strpos($textsnip, strtolower($query));
			if($pos_term > 130)
			{
				$start_pos = $pos_term - 130; 
			}
			$end_pos = $start_pos + 260;
			if(strlen($textsnip) < $end_pos)
			{
				$end_pos = strlen($textsnip) - 1;
				$trim_end = "";
			}
			else
			{
				$trim_end = "....";
			}
				if($start > 0)
					$trim_beg = "....";
				else
					$trim_beg = "";
				$textsnip = $trim_beg.substr($textsnip , $start_pos , $end_pos - $start_pos).$trim_end;
  		}
  		else
  		{
  			$textsnip = $sent;
  			$pos_term = 0;
		  	$start_pos = 0;
		  	$end_pos = 0;
		  	$qflag = 0;
			for($i = 0 ; $i < sizeof($query_terms); $i++)
		  	{
		  		if (strpos(strtolower($textsnip), strtolower($query_terms[$i])) != 0) 
				{
				  $qflag += 1;
				  $pos_term = strpos(strtolower($textsnip), strtolower($query_terms[$i]));
				  break;
				}
			}
			if($pos_term > 130)
			{
				$start_pos = $pos_term - 130; 
			}
			$end_pos = $start_pos + 260;
			if(strlen($textsnip) < $end_pos)
			{
				$end_pos = strlen($textsnip) - 1;
				$trim_end = "";
			}
			else
			{
				$trim_end = "....";
			}
				if($start > 0)
					$trim_beg = "....";
				else
					$trim_beg = "";
				$textsnip = $trim_beg.substr($textsnip , $start_pos , $end_pos - $start_pos).$trim_end;
			if ($qflag == 0)
				$textsnip = "N/A";
  		}
  	if($textsnip == "")
		$textsnip = "N/A";

                
  	echo    "<div'>Title : <a href = '$url'>$title</a></br></div>";
	echo	"<div'>URL : <a href = '$url'>$url</a></br></div>";
	echo   	"<div'>ID : $id</br></div>";
	echo	"<div'>Snippet : ";
		$ary = explode(" ",$textsnip);
		$fullflag = 0;
		$textsnipper = "";
		
		$textsnipper = $textsnip;
		for($i = 0 ; $i < sizeof($query_terms); $i++)
	  	{
	  		$textsnipper = str_ireplace($query_terms[$i], "<b>".$query_terms[$i]."</b>",$textsnipper);
		}
		echo $textsnipper."</div>";
                echo '</div><br/><br/>';
	}
}
?>
	</body>
</html>