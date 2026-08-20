const express = require('express');
const yts = require('yt-search');
const { YoutubeTranscript } = require('youtube-transcript');
const path = require('path');

const app = express();
const PORT = 3000;

app.use(express.static(path.join(__dirname, 'public')));
app.use(express.json());

// Dicionário de relevância para IA
const aiKeywords = [
    'inteligência artificial', 'inteligencia artificial', 'ia', 'ai', 'gpt', 'chatgpt',
    'openai', 'machine learning', 'deep learning', 'algoritmo', 'tecnologia', 'futuro',
    'robô', 'robo', 'automação', 'automacao', 'llm', 'neural', 'copilot', 'gemini'
];

function isRelevantToAI(title, description) {
    const text = `${title} ${description}`.toLowerCase();
    return aiKeywords.some(keyword => text.includes(keyword));
}

// Endpoint de busca
app.get('/api/search', async (req, res) => {
    const query = req.query.q;
    if (!query) {
        return res.status(400).json({ error: 'Query parameter "q" is required' });
    }

    try {
        // Acrescenta &sp=EgIoAQ%253D%253D (filtro de legendas CC) se o usuário buscar por algo
        // yt-search não suporta parâmetros de URL diretamente de forma simples, mas podemos buscar normalmente
        // e filtrar, ou fazer uma busca direta. Vamos buscar normalmente.
        const results = await yts(query);
        const videos = results.videos.slice(0, 20);

        const processed = videos.map(v => ({
            id: v.videoId,
            title: v.title,
            channel: v.author.name,
            duration: v.timestamp,
            views: v.views,
            ago: v.ago,
            url: v.url,
            thumbnail: `https://img.youtube.com/vi/${v.videoId}/mqdefault.jpg`,
            isAiRelevant: isRelevantToAI(v.title, v.description)
        }));

        res.json(processed);
    } catch (error) {
        console.error('Error during search:', error);
        res.status(500).json({ error: 'Failed to search videos: ' + error.message });
    }
});

// Endpoint para pegar detalhes do vídeo + transcrição
app.get('/api/transcript', async (req, res) => {
    const videoId = req.query.id;
    if (!videoId) {
        return res.status(400).json({ error: 'Video ID parameter "id" is required' });
    }

    try {
        // Tenta buscar transcrição
        let transcript = null;
        let errorMsg = null;

        try {
            // Tenta em português
            transcript = await YoutubeTranscript.fetchTranscript(videoId, { lang: 'pt' });
        } catch (e) {
            try {
                // Tenta sem especificar idioma
                transcript = await YoutubeTranscript.fetchTranscript(videoId);
            } catch (e2) {
                errorMsg = e2.message;
            }
        }

        if (!transcript) {
            return res.status(404).json({ error: 'Não foi possível encontrar a transcrição para este vídeo. ' + errorMsg });
        }

        const text = transcript.map(t => t.text).join(' ');

        // Busca metadados adicionais do vídeo usando yt-search (pelo ID)
        let videoMeta = {};
        try {
            const searchResult = await yts({ videoId: videoId });
            videoMeta = {
                title: searchResult.title,
                description: searchResult.description,
                channel: searchResult.author.name,
                url: searchResult.url,
                thumbnail: `https://img.youtube.com/vi/${videoId}/maxresdefault.jpg`
            };
        } catch (e) {
            videoMeta = {
                title: 'Vídeo ' + videoId,
                description: '',
                channel: 'YouTube',
                url: `https://www.youtube.com/watch?v=${videoId}`,
                thumbnail: `https://img.youtube.com/vi/${videoId}/maxresdefault.jpg`
            };
        }

        res.json({
            videoId: videoId,
            title: videoMeta.title,
            description: videoMeta.description,
            channel: videoMeta.channel,
            url: videoMeta.url,
            thumbnail: videoMeta.thumbnail,
            transcript: text
        });
    } catch (error) {
        console.error('Error fetching transcript:', error);
        res.status(500).json({ error: 'Failed to extract transcript: ' + error.message });
    }
});

app.listen(PORT, () => {
    console.log(`Server running on http://localhost:${PORT}`);
});
