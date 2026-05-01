import React from "react";
import { Bell, Search, User } from "lucide-react";
import { useAuth } from "../contexts/AuthContext";

export function AppNavbar() {
  const { profile } = useAuth();

  return (
    <header className="h-16 bg-white border-b border-border flex items-center justify-between px-8 sticky top-0 z-10">
      <div className="flex items-center gap-4 bg-muted/50 px-4 py-2 rounded-md w-96">
        <Search className="w-4 h-4 text-muted-foreground" />
        <input 
          type="text" 
          placeholder="Search tickets, users..." 
          className="bg-transparent border-none outline-none text-sm w-full"
        />
      </div>

      <div className="flex items-center gap-6">
        <button className="relative text-muted-foreground hover:text-foreground transition-colors">
          <Bell className="w-5 h-5" />
          <span className="absolute -top-1 -right-1 w-2 h-2 bg-destructive rounded-full" />
        </button>
        
        <div className="flex items-center gap-3 pl-6 border-l border-border">
          <div className="text-right hidden sm:block">
            <div className="text-sm font-semibold">{profile?.name || "User"}</div>
            <div className="text-[10px] uppercase tracking-wider text-muted-foreground font-bold">{profile?.role || "Guest"}</div>
          </div>
          <div className="w-10 h-10 bg-muted rounded-full flex items-center justify-center">
            <User className="w-6 h-6 text-muted-foreground" />
          </div>
        </div>
      </div>
    </header>
  );
}
