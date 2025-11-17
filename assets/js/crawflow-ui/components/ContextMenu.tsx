import React, { useRef, useEffect } from 'react';
import { TrashIcon, DocumentDuplicateIcon } from './icons';

interface ContextMenuProps {
  top: number;
  left: number;
  onClose: () => void;
  onDelete: () => void;
  onDuplicate: () => void;
}

const ContextMenu: React.FC<ContextMenuProps> = ({ top, left, onClose, onDelete, onDuplicate }) => {
  const menuRef = useRef<HTMLDivElement>(null);

  useEffect(() => {
    const handleClickOutside = (event: MouseEvent) => {
      if (menuRef.current && !menuRef.current.contains(event.target as Node)) {
        onClose();
      }
    };
    document.addEventListener('mousedown', handleClickOutside);
    return () => {
      document.removeEventListener('mousedown', handleClickOutside);
    };
  }, [onClose]);

  return (
    <div
      ref={menuRef}
      style={{ top, left }}
      className="absolute z-50 w-48 bg-white rounded-md shadow-lg border border-gray-200 animate-fade-in-fast"
    >
      <ul className="py-1 text-sm text-gray-700">
        <li>
          <button
            onClick={onDuplicate}
            className="w-full text-left flex items-center gap-3 px-4 py-2 hover:bg-gray-100"
          >
            <DocumentDuplicateIcon />
            <span>Duplicate</span>
          </button>
        </li>
        <li>
          <button
            onClick={onDelete}
            className="w-full text-left flex items-center gap-3 px-4 py-2 text-red-600 hover:bg-red-50"
          >
            <TrashIcon />
            <span>Delete</span>
          </button>
        </li>
      </ul>
    </div>
  );
};

export default ContextMenu;
