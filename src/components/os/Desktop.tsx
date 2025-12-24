import React from 'react';
import { useOS } from '@/contexts/OSContext';
import { useTheme } from '@/contexts/ThemeContext';
import DesktopIcons from './DesktopIcons';
import Taskbar from './Taskbar';
import WindowManager from './WindowManager';
import StartMenu from './StartMenu';

const Desktop: React.FC = () => {
  const { closeStartMenu, isStartMenuOpen } = useOS();
  const { theme } = useTheme();

  return (
    <div className="fixed inset-0 bg-desktop overflow-hidden select-none">
      {/* Desktop Background */}
      <div className="absolute inset-0 overflow-hidden">
        <div className="absolute top-0 left-0 w-full h-full bg-gradient-to-br from-background via-desktop to-background" />
        
        {theme === 'liquid-glass' ? (
          /* Liquid Glass Theme Background */
          <>
            <div className="liquid-orb liquid-orb-1" />
            <div className="liquid-orb liquid-orb-2" />
            <div className="liquid-orb liquid-orb-3" />
            <div className="absolute inset-0 bg-gradient-to-br from-purple-900/10 via-transparent to-cyan-900/10" />
          </>
        ) : (
          /* Default Theme Background */
          <>
            <div className="absolute top-1/4 -left-32 w-[500px] h-[500px] bg-primary/5 rounded-full blur-3xl" />
            <div className="absolute bottom-1/4 -right-32 w-[500px] h-[500px] bg-accent/5 rounded-full blur-3xl" />
            <div className="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[800px] h-[800px] bg-primary/3 rounded-full blur-3xl" />
          </>
        )}
      </div>

      {/* Desktop Icons Area */}
      <div 
        className="absolute inset-0 bottom-14 p-4"
        onClick={closeStartMenu}
      >
        <DesktopIcons />
      </div>

      {/* Windows */}
      <WindowManager />

      {/* Start Menu */}
      {isStartMenuOpen && <StartMenu />}

      {/* Taskbar */}
      <Taskbar />
    </div>
  );
};

export default Desktop;
