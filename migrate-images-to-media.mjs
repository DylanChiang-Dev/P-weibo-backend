import axios from 'axios';
import { createWriteStream, unlinkSync } from 'fs';
import { pipeline } from 'stream/promises';
import { createReadStream } from 'fs';
import FormData from 'form-data';
import path from 'path';
import { fileURLToPath } from 'url';
import { dirname } from 'path';

const __filename = fileURLToPath(import.meta.url);
const __dirname = dirname(__filename);

const NEW_BLOG_API = 'https://pyqapi.3331322.xyz';
const NEW_BLOG_TOKEN = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJodHRwczpcL1wvcHlxYXBpLjMzMzEzMjIueHl6Iiwic3ViIjoxLCJpYXQiOjE3NjQ0MjcwNjksImV4cCI6MTc2NDQyNzk2OSwianRpIjoiYjljNDIzMjExZmM3ZTY4ZjlkMjc0NzBmMWEzZGQ2MzgifQ.KS6Bf_ehSzMfMJ89brsZ7kza6BN8XPRRdc_CuB2uaJM';

// 下载图片
async function downloadImage(url, filepath) {
    const response = await axios({
        url,
        method: 'GET',
        responseType: 'stream',
        timeout: 30000
    });
    await pipeline(response.data, createWriteStream(filepath));
}

// 上传图片到媒体库
async function uploadToMediaLibrary(filepath, token) {
    const form = new FormData();
    form.append('files[]', createReadStream(filepath));

    const response = await axios.post(`${NEW_BLOG_API}/api/media`, form, {
        headers: {
            ...form.getHeaders(),
            'Authorization': `Bearer ${token}`
        }
    });

    if (response.data.success && response.data.data.items?.[0]) {
        return response.data.data.items[0].url;
    }
    throw new Error('Upload failed');
}

// 处理文章中的图片
async function processArticleImages(article, token, imageCache, tempDir) {
    console.log(`\n处理文章: ${article.title}`);

    // 检查content是否存在
    if (!article.content) {
        console.log('  ⚠️  文章内容为空，跳过');
        return null;
    }

    // 匹配所有WordPress图片URL (包括blog.和不含blog.的)
    const imageRegex = /!\[([^\]]*)\]\((https?:\/\/(?:blog\.)?3331322\.xyz\/wp-content\/[^)]+)\)/g;
    let content = article.content;
    let coverImage = article.cover_image;
    let hasChanges = false;

    const matches = [...content.matchAll(imageRegex)];

    if (matches.length === 0 && !coverImage?.includes('wp-content')) {
        console.log('  ✓ 无需处理图片');
        return null;
    }

    console.log(`  发现 ${matches.length} 张内容图片`);

    // 处理内容中的图片
    for (const match of matches) {
        const [fullMatch, alt, originalUrl] = match;

        try {
            // 修复错误的域名
            const fixedUrl = originalUrl.replace(/^https?:\/\/3331322\.xyz\//i, 'https://blog.3331322.xyz/');

            // 检查缓存
            if (imageCache[originalUrl] || imageCache[fixedUrl]) {
                const cachedUrl = imageCache[originalUrl] || imageCache[fixedUrl];
                content = content.replace(fullMatch, `![${alt}](${cachedUrl})`);
                hasChanges = true;
                continue;
            }

            console.log(`  📥 下载: ${path.basename(fixedUrl)}`);

            // 下载图片
            const filename = path.basename(new URL(fixedUrl).pathname);
            const filepath = path.join(tempDir, filename);

            await downloadImage(fixedUrl, filepath);

            // 上传到媒体库
            console.log(`  📤 上传到媒体库...`);
            const newUrl = await uploadToMediaLibrary(filepath, token);

            // 缓存映射 (同时缓存原始URL和修复后的URL)
            imageCache[originalUrl] = newUrl;
            imageCache[fixedUrl] = newUrl;

            // 替换URL
            content = content.replace(fullMatch, `![${alt}](${newUrl})`);
            hasChanges = true;

            console.log(`  ✅ 已迁移: ${newUrl.substring(0, 60)}...`);

            // 清理临时文件
            unlinkSync(filepath);
        } catch (error) {
            console.error(`  ⚠️  处理失败 ${originalUrl}: ${error.message}`);
        }
    }

    // 处理封面图
    if (coverImage && (coverImage.includes('wp-content') || coverImage.includes('3331322.xyz'))) {
        try {
            // 修复错误的域名
            const fixedCoverUrl = coverImage.replace(/^https?:\/\/3331322\.xyz\//i, 'https://blog.3331322.xyz/');

            if (imageCache[coverImage] || imageCache[fixedCoverUrl]) {
                coverImage = imageCache[coverImage] || imageCache[fixedCoverUrl];
                hasChanges = true;
            } else {
                console.log(`  📥 下载封面图: ${path.basename(fixedCoverUrl)}`);

                const filename = path.basename(new URL(fixedCoverUrl).pathname);
                const filepath = path.join(tempDir, filename);

                await downloadImage(fixedCoverUrl, filepath);

                console.log(`  📤 上传封面图到媒体库...`);
                const newUrl = await uploadToMediaLibrary(filepath, token);

                imageCache[coverImage] = newUrl;
                imageCache[fixedCoverUrl] = newUrl;
                coverImage = newUrl;
                hasChanges = true;

                console.log(`  ✅ 封面图已迁移`);

                unlinkSync(filepath);
            }
        } catch (error) {
            console.error(`  ⚠️  封面图处理失败: ${error.message}`);
        }
    }

    if (hasChanges) {
        return {
            ...article,
            content,
            cover_image: coverImage
        };
    }

    return null;
}

// 更新文章
async function updateArticle(article, token) {
    const response = await axios.put(
        `${NEW_BLOG_API}/api/blog/articles/${article.id}`,
        article,
        {
            headers: {
                'Authorization': `Bearer ${token}`,
                'Content-Type': 'application/json'
            }
        }
    );
    return response.data;
}

// 主函数
async function migrateImages() {
    console.log('🚀 开始迁移WordPress图片到媒体库\n');

    if (!NEW_BLOG_TOKEN) {
        console.error('❌ 请先设置 NEW_BLOG_TOKEN');
        console.log('\n获取方法:');
        console.log('1. 登录 http://localhost:4321/admin/login');
        console.log('2. 打开控制台 (F12)');
        console.log('3. 执行: localStorage.getItem("access_token")');
        console.log('4. 将token填入脚本第14行\n');
        return;
    }

    // 创建临时文件夹
    const tempDir = path.join(__dirname, 'temp');
    try {
        await import('fs/promises').then(fs => fs.mkdir(tempDir, { recursive: true }));
    } catch (error) {
        console.error('无法创建临时文件夹:', error.message);
        return;
    }

    try {
        // 先获取文章列表（只包含基本信息）
        console.log('📥 获取文章列表...');
        const listResponse = await axios.get(`${NEW_BLOG_API}/api/blog/articles?limit=200`, {
            headers: {
                'Authorization': `Bearer ${NEW_BLOG_TOKEN}`
            }
        });

        const articleList = listResponse.data.data.items;
        console.log(`✅ 找到 ${articleList.length} 篇文章\n`);

        // 图片URL缓存
        const imageCache = {};
        let processedCount = 0;
        let updatedCount = 0;
        let errorCount = 0;

        for (let i = 0; i < articleList.length; i++) {
            const articleBasic = articleList[i];
            console.log(`\n[${i + 1}/${articleList.length}] ${articleBasic.title}`);

            try {
                // 获取完整文章详情（包含content）
                console.log('  📥 获取文章详情...');
                const detailResponse = await axios.get(`${NEW_BLOG_API}/api/blog/articles/${articleBasic.id}`, {
                    headers: {
                        'Authorization': `Bearer ${NEW_BLOG_TOKEN}`
                    }
                });

                const article = detailResponse.data.data;

                const updatedArticle = await processArticleImages(article, NEW_BLOG_TOKEN, imageCache, tempDir);

                if (updatedArticle) {
                    console.log('  💾 保存文章...');
                    await updateArticle(updatedArticle, NEW_BLOG_TOKEN);
                    console.log('  ✅ 文章已更新');
                    updatedCount++;
                }

                processedCount++;
            } catch (error) {
                console.error(`  ❌ 处理失败: ${error.message}`);
                errorCount++;
            }
        }

        console.log('\n' + '='.repeat(80));
        console.log('✨ 迁移完成！');
        console.log(`📊 处理: ${processedCount} 篇`);
        console.log(`✅ 更新: ${updatedCount} 篇`);
        console.log(`❌ 失败: ${errorCount} 篇`);
        console.log(`📷 图片缓存: ${Object.keys(imageCache).length} 张`);
        console.log('='.repeat(80));

    } catch (error) {
        console.error('错误:', error.message);
        if (error.response) {
            console.error('API响应:', error.response.data);
        }
    }
}

// 运行迁移
migrateImages().catch(console.error);
