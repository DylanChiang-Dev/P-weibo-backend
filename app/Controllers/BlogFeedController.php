<?php
namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Models\BlogArticle;

class BlogFeedController {
    /**
     * Generate RSS 2.0 Feed
     */
    public function rss(Request $req): void {
        $articles = BlogArticle::list(20, null, 'published');
        
        $config = \config();
        $baseUrl = $config['frontend_origin'] ?? 'https://blog.example.com';
        
        header('Content-Type: application/rss+xml; charset=UTF-8');
        
        echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        echo '<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">' . "\n";
        echo '<channel>' . "\n";
        echo '  <title>我的博客</title>' . "\n";
        echo '  <link>' . htmlspecialchars($baseUrl) . '</link>' . "\n";
        echo '  <description>个人博客 - 技术与生活</description>' . "\n";
        echo '  <language>zh-CN</language>' . "\n";
        echo '  <atom:link href="' . htmlspecialchars($baseUrl . '/rss.xml') . '" rel="self" type="application/rss+xml" />' . "\n";
        
        foreach ($articles as $article) {
            $articleUrl = $baseUrl . '/articles/' . $article['slug'];
            $pubDate = date('r', strtotime($article['published_at'] ?? $article['created_at']));
            
            echo '  <item>' . "\n";
            echo '    <title>' . htmlspecialchars($article['title']) . '</title>' . "\n";
            echo '    <link>' . htmlspecialchars($articleUrl) . '</link>' . "\n";
            echo '    <guid isPermaLink="true">' . htmlspecialchars($articleUrl) . '</guid>' . "\n";
            echo '    <description><![CDATA[' . ($article['excerpt'] ?? '') . ']]></description>' . "\n";
            echo '    <pubDate>' . $pubDate . '</pubDate>' . "\n";
            
            if (!empty($article['email'])) {
                echo '    <author>' . htmlspecialchars($article['email']) . '</author>' . "\n";
            }
            
            echo '  </item>' . "\n";
        }
        
        echo '</channel>' . "\n";
        echo '</rss>';
        
        exit;
    }

    /**
     * Generate Sitemap XML
     */
    public function sitemap(Request $req): void {
        $articles = BlogArticle::list(1000, null, 'published');
        
        $config = \config();
        $baseUrl = $config['frontend_origin'] ?? 'https://blog.example.com';
        
        header('Content-Type: application/xml; charset=UTF-8');
        
        echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        
        // Homepage
        echo '  <url>' . "\n";
        echo '    <loc>' . htmlspecialchars($baseUrl) . '</loc>' . "\n";
        echo '    <changefreq>daily</changefreq>' . "\n";
        echo '    <priority>1.0</priority>' . "\n";
        echo '  </url>' . "\n";
        
        // Articles
        foreach ($articles as $article) {
            $articleUrl = $baseUrl . '/articles/' . $article['slug'];
            $lastmod = date('Y-m-d', strtotime($article['updated_at']));
            
            echo '  <url>' . "\n";
            echo '    <loc>' . htmlspecialchars($articleUrl) . '</loc>' . "\n";
            echo '    <lastmod>' . $lastmod . '</lastmod>' . "\n";
            echo '    <changefreq>weekly</changefreq>' . "\n";
            echo '    <priority>0.8</priority>' . "\n";
            echo '  </url>' . "\n";
        }
        
        echo '</urlset>';
        
        exit;
    }

    /**
     * Get archive statistics (by month/year)
     */
    public function archives(Request $req): void {
        $db = \App\Core\Database::getInstance();
        $pdo = $db->getPdo();
        
        $sql = "
            SELECT 
                YEAR(published_at) as year,
                MONTH(published_at) as month,
                COUNT(*) as count
            FROM blog_articles
            WHERE status = 'published'
            AND published_at IS NOT NULL
            GROUP BY YEAR(published_at), MONTH(published_at)
            ORDER BY year DESC, month DESC
        ";
        
        $stmt = $pdo->query($sql);
        $archives = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        $formatted = array_map(function($item) {
            return [
                'year' => (int)$item['year'],
                'month' => (int)$item['month'],
                'count' => (int)$item['count']
            ];
        }, $archives);
        
        \App\Core\ApiResponse::success($formatted);
    }

    /**
     * Get articles by archive (year/month)
     */
    public function archiveArticles(Request $req, array $params): void {
        $year = (int)($params['year'] ?? 0);
        $month = (int)($params['month'] ?? 0);
        
        if ($year < 2000 || $year > 2100 || $month < 1 || $month > 12) {
            throw new \App\Exceptions\ValidationException('Invalid year or month');
        }
        
        $db = \App\Core\Database::getInstance();
        $pdo = $db->getPdo();
        
        $sql = "
            SELECT * FROM blog_articles
            WHERE status = 'published'
            AND YEAR(published_at) = :year
            AND MONTH(published_at) = :month
            ORDER BY published_at DESC
        ";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['year' => $year, 'month' => $month]);
        $articles = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        // Format articles
        $blogService = new \App\Services\BlogService();
        $formatted = [];
        foreach ($articles as $article) {
            $articleId = (int)$article['id'];
            $article['categories'] = \App\Models\BlogArticle::getCategories($articleId);
            $article['tags'] = \App\Models\BlogArticle::getTags($articleId);
            $formatted[] = $article;
        }
        
        \App\Core\ApiResponse::success($formatted);
    }
}
?>
