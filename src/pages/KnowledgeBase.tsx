import React, { useState } from "react";
import { 
  BookOpen, 
  Search, 
  ThumbsUp, 
  Eye, 
  ChevronRight,
  Tag,
  Clock,
  User,
  Star
} from "lucide-react";
import { Button } from "@/components/ui/button";
import { cn } from "@/lib/utils";

const KNOWLEDGE_ARTICLES = [
  { id: "KB001", title: "How to Connect to Corporate VPN", category: "Network", views: 1250, rating: 4.8, author: "IT Security", updatedAt: "2026-03-15" },
  { id: "KB002", title: "Setting up Email on Mobile Devices", category: "Messaging", views: 840, rating: 4.5, author: "Service Desk", updatedAt: "2026-04-01" },
  { id: "KB003", title: "Troubleshooting ERP Login Issues", category: "Applications", views: 2100, rating: 4.2, author: "App Support", updatedAt: "2026-04-10" },
  { id: "KB004", title: "Password Reset Self-Service Guide", category: "Access", views: 5600, rating: 4.9, author: "Identity Team", updatedAt: "2026-01-20" },
];

export function KnowledgeBase() {
  const [articles] = useState(KNOWLEDGE_ARTICLES);
  const [searchQuery, setSearchQuery] = useState("");

  const filteredArticles = articles.filter(a => 
    a.title.toLowerCase().includes(searchQuery.toLowerCase()) ||
    a.category.toLowerCase().includes(searchQuery.toLowerCase())
  );

  return (
    <div className="space-y-8 max-w-7xl mx-auto">
      <div className="text-center space-y-4 py-12 bg-sn-sidebar text-white rounded-2xl shadow-xl relative overflow-hidden">
        <div className="absolute inset-0 bg-gradient-to-br from-sn-green/10 to-transparent pointer-events-none" />
        <h1 className="text-4xl font-light relative z-10">Knowledge Base</h1>
        <p className="text-white/60 relative z-10">Find answers, troubleshooting guides, and documentation.</p>
        <div className="max-w-2xl mx-auto px-4 relative z-10">
          <div className="relative group">
            <Search className="w-5 h-5 absolute left-4 top-1/2 -translate-y-1/2 text-muted-foreground group-focus-within:text-sn-green transition-colors" />
            <input 
              type="text"
              placeholder="Search for articles, topics, or keywords..."
              value={searchQuery}
              onChange={(e) => setSearchQuery(e.target.value)}
              className="w-full bg-white text-sn-dark border-none rounded-xl py-4 pl-12 pr-4 text-lg outline-none shadow-2xl focus:ring-2 focus:ring-sn-green transition-all"
            />
          </div>
        </div>
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-4 gap-8">
        {/* Sidebar Categories */}
        <div className="space-y-6">
          <div className="sn-card p-4">
            <h3 className="text-xs font-bold uppercase tracking-wider text-muted-foreground mb-4">Categories</h3>
            <div className="space-y-1">
              {["Applications", "Hardware", "Network", "Security", "Messaging", "Access"].map(cat => (
                <button key={cat} className="w-full flex items-center justify-between px-3 py-2 rounded-lg text-sm text-sn-dark hover:bg-muted/50 transition-colors group">
                  <span>{cat}</span>
                  <ChevronRight className="w-3 h-3 text-muted-foreground group-hover:text-sn-green" />
                </button>
              ))}
            </div>
          </div>

          <div className="sn-card p-4 bg-muted/30 border-dashed">
            <h3 className="text-xs font-bold uppercase tracking-wider text-muted-foreground mb-4">Top Contributors</h3>
            <div className="space-y-4">
              {[
                { name: "Sarah Chen", articles: 42 },
                { name: "Mike Ross", articles: 28 },
                { name: "Elena Gilbert", articles: 15 },
              ].map(user => (
                <div key={user.name} className="flex items-center gap-3">
                  <div className="w-8 h-8 bg-sn-green/20 rounded-full flex items-center justify-center text-[10px] font-bold text-sn-green">
                    {user.name.split(' ').map(n => n[0]).join('')}
                  </div>
                  <div>
                    <div className="text-xs font-bold">{user.name}</div>
                    <div className="text-[10px] text-muted-foreground">{user.articles} articles</div>
                  </div>
                </div>
              ))}
            </div>
          </div>
        </div>

        {/* Article List */}
        <div className="lg:col-span-3 space-y-4">
          <div className="flex items-center justify-between mb-2">
            <h2 className="text-xl font-bold text-sn-dark">Featured Articles</h2>
            <div className="flex items-center gap-2">
              <Button variant="ghost" size="sm" className="text-xs">Most Recent</Button>
              <Button variant="ghost" size="sm" className="text-xs font-bold text-sn-green">Most Viewed</Button>
            </div>
          </div>

          <div className="grid grid-cols-1 gap-4">
            {filteredArticles.map((article) => (
              <div key={article.id} className="sn-card p-6 hover:border-sn-green transition-all group cursor-pointer">
                <div className="flex items-start justify-between gap-4">
                  <div className="space-y-3 flex-grow">
                    <div className="flex items-center gap-3">
                      <span className="text-[10px] font-mono font-bold text-blue-600">{article.id}</span>
                      <div className="flex items-center gap-1 text-[10px] font-bold text-orange-500">
                        <Star className="w-3 h-3 fill-current" /> {article.rating}
                      </div>
                      <span className="px-2 py-0.5 rounded bg-muted text-[10px] font-bold uppercase text-muted-foreground">
                        {article.category}
                      </span>
                    </div>
                    <h3 className="text-xl font-bold text-sn-dark group-hover:text-sn-green transition-colors">
                      {article.title}
                    </h3>
                    <div className="flex items-center gap-6 text-xs text-muted-foreground">
                      <div className="flex items-center gap-1.5">
                        <User className="w-3.5 h-3.5" /> {article.author}
                      </div>
                      <div className="flex items-center gap-1.5">
                        <Eye className="w-3.5 h-3.5" /> {article.views} views
                      </div>
                      <div className="flex items-center gap-1.5">
                        <Clock className="w-3.5 h-3.5" /> Updated {article.updatedAt}
                      </div>
                    </div>
                  </div>
                  <div className="flex flex-col items-end gap-4">
                    <Button variant="ghost" size="icon" className="group-hover:bg-sn-green/10">
                      <ThumbsUp className="w-4 h-4 text-muted-foreground group-hover:text-sn-green" />
                    </Button>
                    <ChevronRight className="w-5 h-5 text-muted-foreground group-hover:text-sn-green transition-all" />
                  </div>
                </div>
              </div>
            ))}
          </div>
        </div>
      </div>
    </div>
  );
}
