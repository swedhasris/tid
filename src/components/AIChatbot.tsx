import React, { useState, useRef, useEffect } from 'react';
import { MessageSquare, X, Send, Bot, User, Loader2, Sparkles } from 'lucide-react';

interface ChatMessage {
  id: string;
  sender: 'ai' | 'user';
  text: string;
}

const API_KEY = 'AIzaSyB_7g4j9SemDL7MUDoJUCn2EuTQ3xhfCPI';
const MODEL   = 'gemini-2.0-flash';

const SYSTEM_PROMPT = `You are Kiru, a highly intelligent and friendly AI assistant built into the Connect IT service management platform.

## Who You Are
- Warm, witty, and genuinely helpful — like a brilliant friend who knows everything
- You adapt your tone: casual and fun for small talk, precise and detailed for technical questions
- You never sound robotic or corporate — you sound human and approachable

## Your Expertise
- IT Service Management (ITSM): incidents, tickets, SLA, change management, CMDB, problem management
- Software development: PHP, JavaScript, TypeScript, React, SQL, REST APIs
- General knowledge: science, math, history, writing, creative ideas
- Debugging, code review, architecture advice, best practices

## How You Respond
- Keep responses concise but complete — no unnecessary filler
- Use bullet points, numbered steps, or code blocks when it helps clarity
- For code questions, always provide working, copy-paste-ready examples
- For IT questions, give actionable advice like a senior consultant would
- Anticipate follow-up questions and address them proactively
- Use emojis sparingly — only when they genuinely add warmth

## Personality
- Encouraging: celebrate the user's efforts and progress
- Patient: never make anyone feel bad for asking anything
- Honest: give real opinions when asked, not vague "it depends" answers
- Proactive: suggest better approaches when you spot them

## Boundaries
- Never provide harmful, illegal, or unethical information
- Don't make up facts — if uncertain, say so clearly
- Stay focused and relevant to what the user needs

Your goal: make every interaction feel like talking to the smartest, most helpful person the user knows.`;

/** Render plain text with basic markdown-like formatting */
function MessageText({ text }: { text: string }) {
  const lines = text.split('\n');
  return (
    <div className="space-y-1 text-sm leading-relaxed">
      {lines.map((line, i) => {
        if (line.startsWith('```')) return null;
        if (line.startsWith('### ')) return <p key={i} className="font-bold text-base">{line.slice(4)}</p>;
        if (line.startsWith('## '))  return <p key={i} className="font-bold">{line.slice(3)}</p>;
        if (line.startsWith('# '))   return <p key={i} className="font-bold text-lg">{line.slice(2)}</p>;
        if (line.startsWith('- ') || line.startsWith('* ')) {
          return <p key={i} className="pl-3">• {line.slice(2)}</p>;
        }
        if (/^\d+\. /.test(line)) return <p key={i} className="pl-3">{line}</p>;
        if (line.trim() === '') return <div key={i} className="h-1" />;
        // Bold **text**
        const parts = line.split(/(\*\*[^*]+\*\*)/g);
        return (
          <p key={i}>
            {parts.map((part, j) =>
              part.startsWith('**') && part.endsWith('**')
                ? <strong key={j}>{part.slice(2, -2)}</strong>
                : part
            )}
          </p>
        );
      })}
    </div>
  );
}

export function AIChatbot() {
  const [isOpen, setIsOpen]       = useState(false);
  const [input, setInput]         = useState('');
  const [isLoading, setIsLoading] = useState(false);
  const [retryCountdown, setRetryCountdown] = useState(0);
  const [messages, setMessages]   = useState<ChatMessage[]>([
    {
      id: '1',
      sender: 'ai',
      text: "Hey there! 👋 I'm **Kiru**, your AI assistant powered by Gemini 2.5 Pro.\n\nI can help with:\n- IT tickets, incidents & SLA questions\n- Coding in PHP, React, TypeScript\n- General questions & brainstorming\n\nWhat's on your mind?",
    },
  ]);
  const messagesEndRef = useRef<HTMLDivElement>(null);

  useEffect(() => {
    if (isOpen) messagesEndRef.current?.scrollIntoView({ behavior: 'smooth' });
  }, [messages, isOpen]);

  const callGemini = async (text: string): Promise<string> => {
    const res = await fetch(
      `https://generativelanguage.googleapis.com/v1beta/models/${MODEL}:generateContent?key=${API_KEY}`,
      {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          system_instruction: { parts: [{ text: SYSTEM_PROMPT }] },
          contents: [{ role: 'user', parts: [{ text }] }],
          generationConfig: { temperature: 0.8, topK: 40, topP: 0.95, maxOutputTokens: 2048 },
        }),
      }
    );

    const data = await res.json();

    if (!res.ok) {
      const msg = data?.error?.message || `Error ${res.status}`;
      const retryMatch = msg.match(/retry in ([\d.]+)s/i);
      const waitSecs = retryMatch ? Math.ceil(parseFloat(retryMatch[1])) : (res.status === 429 ? 60 : 0);
      const isNoQuota = msg.includes('limit: 0') || (msg.toLowerCase().includes('quota') && waitSecs === 0);
      if (isNoQuota) {
        const err: any = new Error('🔑 Your API key has no quota remaining.\n\nTo fix this:\n1. Go to https://aistudio.google.com/apikey\n2. Create a new key with a different Google account\n3. Update GEMINI_API_KEY in your .env file\n4. Restart the dev server');
        err.retryAfter = 0;
        throw err;
      }
      const err: any = new Error(msg);
      err.retryAfter = waitSecs;
      throw err;
    }

    return data?.candidates?.[0]?.content?.parts?.[0]?.text || "I couldn't generate a response. Please try again.";
  };

  const handleSend = async () => {
    if (!input.trim() || isLoading || retryCountdown > 0) return;

    const userMsg: ChatMessage = { id: Date.now().toString(), sender: 'user', text: input.trim() };
    setMessages(prev => [...prev, userMsg]);
    setInput('');
    setIsLoading(true);

    try {
      let reply: string;
      try {
        reply = await callGemini(userMsg.text);
      } catch (err: any) {
        // Auto-retry after rate limit
        if (err.retryAfter > 0) {
          let secs = err.retryAfter;
          setIsLoading(false);
          setRetryCountdown(secs);
          const timer = setInterval(() => {
            secs--;
            setRetryCountdown(secs);
            if (secs <= 0) {
              clearInterval(timer);
              setRetryCountdown(0);
              // Retry automatically
              setIsLoading(true);
              callGemini(userMsg.text)
                .then(r => setMessages(prev => [...prev, { id: Date.now().toString(), sender: 'ai', text: r }]))
                .catch(e => setMessages(prev => [...prev, { id: Date.now().toString(), sender: 'ai', text: `Sorry, still having trouble: ${e.message}` }]))
                .finally(() => setIsLoading(false));
            }
          }, 1000);
          return;
        }
        throw err;
      }
      setMessages(prev => [...prev, { id: Date.now().toString(), sender: 'ai', text: reply }]);
    } catch (err: any) {
      setMessages(prev => [...prev, { id: Date.now().toString(), sender: 'ai', text: `Sorry, something went wrong: ${err.message}` }]);
    } finally {
      setIsLoading(false);
    }
  };

  const quickPrompts = ['Help with a ticket', 'Explain SLA', 'PHP code help', 'What can you do?'];

  return (
    <div className="fixed bottom-6 right-6 z-50">
      {isOpen && (
        <div className="mb-4 rounded-2xl shadow-2xl flex flex-col overflow-hidden border border-gray-200 dark:border-gray-700"
             style={{ width: '380px', height: '580px', background: 'var(--background, #fff)' }}>

          {/* Header */}
          <div className="flex items-center justify-between px-4 py-3"
               style={{ background: 'linear-gradient(135deg, #1d1d2c 0%, #2d3748 100%)' }}>
            <div className="flex items-center gap-2">
              <div className="w-8 h-8 rounded-full bg-sn-green flex items-center justify-center">
                <Sparkles size={16} className="text-white" />
              </div>
              <div>
                <div className="text-white font-bold text-sm">Kiru AI</div>
                <div className="text-green-400 text-[10px] flex items-center gap-1">
                  <span className="w-1.5 h-1.5 rounded-full bg-green-400 inline-block animate-pulse" />
                  Gemini 2.5 Pro · Online
                </div>
              </div>
            </div>
            <button onClick={() => setIsOpen(false)} className="text-gray-400 hover:text-white transition-colors p-1 rounded">
              <X size={18} />
            </button>
          </div>

          {/* Messages */}
          <div className="flex-1 overflow-y-auto p-4 space-y-4"
               style={{ background: 'var(--muted, #f8fafc)' }}>
            {messages.map(msg => (
              <div key={msg.id} className={`flex gap-2 ${msg.sender === 'user' ? 'justify-end' : 'justify-start'}`}>
                {msg.sender === 'ai' && (
                  <div className="w-7 h-7 rounded-full bg-sn-green flex items-center justify-center flex-shrink-0 mt-1">
                    <Bot size={14} className="text-white" />
                  </div>
                )}
                <div className={`max-w-[80%] px-3 py-2 rounded-2xl ${
                  msg.sender === 'user'
                    ? 'bg-blue-500 text-white rounded-br-sm'
                    : 'bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-gray-800 dark:text-gray-100 rounded-bl-sm shadow-sm'
                }`}>
                  {msg.sender === 'ai' ? <MessageText text={msg.text} /> : <p className="text-sm">{msg.text}</p>}
                </div>
                {msg.sender === 'user' && (
                  <div className="w-7 h-7 rounded-full bg-blue-500 flex items-center justify-center flex-shrink-0 mt-1">
                    <User size={14} className="text-white" />
                  </div>
                )}
              </div>
            ))}

            {(isLoading || retryCountdown > 0) && (
              <div className="flex gap-2 justify-start">
                <div className="w-7 h-7 rounded-full bg-sn-green flex items-center justify-center flex-shrink-0">
                  <Bot size={14} className="text-white" />
                </div>
                <div className="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 px-4 py-3 rounded-2xl rounded-bl-sm shadow-sm flex items-center gap-2">
                  {retryCountdown > 0 ? (
                    <span className="text-xs text-amber-500 font-medium">⏳ Retrying in {retryCountdown}s...</span>
                  ) : (
                    <>
                      <span className="w-2 h-2 bg-sn-green rounded-full animate-bounce" style={{ animationDelay: '0ms' }} />
                      <span className="w-2 h-2 bg-sn-green rounded-full animate-bounce" style={{ animationDelay: '150ms' }} />
                      <span className="w-2 h-2 bg-sn-green rounded-full animate-bounce" style={{ animationDelay: '300ms' }} />
                    </>
                  )}
                </div>
              </div>
            )}
            <div ref={messagesEndRef} />
          </div>

          {/* Quick prompts — only show when no conversation yet */}
          {messages.length === 1 && !isLoading && (
            <div className="px-3 pb-2 flex flex-wrap gap-1.5" style={{ background: 'var(--muted, #f8fafc)' }}>
              {quickPrompts.map(p => (
                <button key={p} onClick={() => { setInput(p); }}
                        className="text-xs px-3 py-1.5 rounded-full border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-600 dark:text-gray-300 hover:border-sn-green hover:text-sn-green transition-colors">
                  {p}
                </button>
              ))}
            </div>
          )}

          {/* Input */}
          <div className="px-3 py-3 border-t border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900">
            <div className="flex items-center gap-2 bg-gray-100 dark:bg-gray-800 rounded-full px-4 py-2">
              <input
                type="text"
                value={input}
                onChange={e => setInput(e.target.value)}
                onKeyDown={e => e.key === 'Enter' && !e.shiftKey && handleSend()}
                placeholder="Ask Kiru anything..."
                className="flex-1 bg-transparent text-sm outline-none text-gray-800 dark:text-gray-100 placeholder-gray-400"
              />
              <button onClick={handleSend} disabled={isLoading || !input.trim() || retryCountdown > 0}
                      className="w-8 h-8 rounded-full bg-sn-green flex items-center justify-center disabled:opacity-40 disabled:cursor-not-allowed hover:opacity-90 transition-opacity flex-shrink-0">
                <Send size={14} className="text-white" />
              </button>
            </div>
            <p className="text-center text-[10px] text-gray-400 mt-1.5">Powered by Gemini 2.5 Pro</p>
          </div>
        </div>
      )}

      {/* FAB */}
      {!isOpen && (
        <button onClick={() => setIsOpen(true)}
                className="w-14 h-14 rounded-full shadow-lg flex items-center justify-center hover:scale-110 transition-transform"
                style={{ background: 'linear-gradient(135deg, #62d84e, #3db82e)' }}>
          <MessageSquare size={24} className="text-white" />
        </button>
      )}
    </div>
  );
}
