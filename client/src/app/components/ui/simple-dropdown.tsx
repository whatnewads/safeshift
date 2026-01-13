import { useState, useRef, useEffect, createContext, useContext } from 'react';
import { cn } from './utils';

interface DropdownContextType {
  closeDropdown: () => void;
}

const DropdownContext = createContext<DropdownContextType | null>(null);

interface SimpleDropdownProps {
  trigger: React.ReactNode;
  children: React.ReactNode;
  align?: 'start' | 'end' | 'center';
  side?: 'top' | 'bottom' | 'left' | 'right';
  className?: string;
}

export function SimpleDropdown({ 
  trigger, 
  children, 
  align = 'start',
  side = 'bottom',
  className 
}: SimpleDropdownProps) {
  const [isOpen, setIsOpen] = useState(false);
  const dropdownRef = useRef<HTMLDivElement>(null);

  const closeDropdown = () => setIsOpen(false);

  useEffect(() => {
    function handleClickOutside(event: MouseEvent) {
      if (dropdownRef.current && !dropdownRef.current.contains(event.target as Node)) {
        setIsOpen(false);
      }
    }

    if (isOpen) {
      document.addEventListener('mousedown', handleClickOutside);
      return () => document.removeEventListener('mousedown', handleClickOutside);
    }
  }, [isOpen]);

  const getPositionClasses = () => {
    const positions = {
      bottom: 'top-full mt-2',
      top: 'bottom-full mb-2',
      left: 'right-full mr-2',
      right: 'left-full ml-2',
    };

    const alignments = {
      start: side === 'bottom' || side === 'top' ? 'left-0' : 'top-0',
      end: side === 'bottom' || side === 'top' ? 'right-0' : 'bottom-0',
      center: side === 'bottom' || side === 'top' ? 'left-1/2 -translate-x-1/2' : 'top-1/2 -translate-y-1/2',
    };

    return `${positions[side]} ${alignments[align]}`;
  };

  return (
    <DropdownContext.Provider value={{ closeDropdown }}>
      <div className="relative" ref={dropdownRef}>
        <div onClick={() => setIsOpen(!isOpen)}>
          {trigger}
        </div>
        
        {isOpen && (
          <div 
            className={cn(
              'absolute z-50 min-w-[8rem] rounded-md border bg-white dark:bg-slate-800 dark:border-slate-700 p-1 shadow-md',
              getPositionClasses(),
              className
            )}
          >
            {children}
          </div>
        )}
      </div>
    </DropdownContext.Provider>
  );
}

interface SimpleDropdownItemProps {
  children: React.ReactNode;
  onClick?: () => void;
  className?: string;
}

export function SimpleDropdownItem({ children, onClick, className }: SimpleDropdownItemProps) {
  const context = useContext(DropdownContext);

  const handleClick = () => {
    if (onClick) {
      onClick();
    }
    if (context) {
      context.closeDropdown();
    }
  };

  return (
    <div
      className={cn(
        'relative flex cursor-pointer items-center rounded-sm px-2 py-1.5 text-sm outline-none hover:bg-slate-100 dark:hover:bg-slate-700 dark:text-slate-200 select-none',
        className
      )}
      onClick={handleClick}
    >
      {children}
    </div>
  );
}

interface SimpleDropdownLabelProps {
  children: React.ReactNode;
  className?: string;
}

export function SimpleDropdownLabel({ children, className }: SimpleDropdownLabelProps) {
  return (
    <div className={cn('px-2 py-1.5 text-sm font-medium dark:text-slate-200', className)}>
      {children}
    </div>
  );
}

export function SimpleDropdownSeparator() {
  return <div className="bg-slate-200 dark:bg-slate-700 -mx-1 my-1 h-px" />;
}